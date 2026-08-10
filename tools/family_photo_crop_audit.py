#!/usr/bin/env python3
"""Render and record privacy-local visual QA for reviewed multi-photo crops."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import tempfile
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

LOCAL_DEPENDENCIES = Path(__file__).resolve().parents[1] / "output" / "family-photo-python-libs"
if LOCAL_DEPENDENCIES.is_dir():
    import sys

    sys.path.insert(0, str(LOCAL_DEPENDENCIES))

import cv2
import numpy as np


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8") as stream:
        return [json.loads(line) for line in stream if line.strip()]


def write_jsonl(path: Path, records: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary = tempfile.mkstemp(prefix=path.name + ".", suffix=".tmp", dir=path.parent)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8", newline="\n") as stream:
            for record in sorted(records, key=lambda value: int(value.get("item_id", 0))):
                stream.write(json.dumps(record, sort_keys=True, separators=(",", ":")) + "\n")
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def decision_digest(decision: dict[str, Any]) -> str:
    payload = json.dumps(
        {
            "item_id": int(decision["item_id"]),
            "decision": decision["decision"],
            "regions": decision.get("regions", []),
            "thumbnail_sha256": decision["census_thumbnail_sha256"],
        },
        sort_keys=True,
        separators=(",", ":"),
    ).encode()
    return hashlib.sha256(payload).hexdigest()


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def audit_page_digest(page: dict[str, Any]) -> str:
    return hashlib.sha256(json.dumps(page, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def crop(image: np.ndarray, region: dict[str, Any]) -> np.ndarray:
    height, width = image.shape[:2]
    x1 = max(0, min(width - 1, round(int(region["x"]) / 10000 * width)))
    y1 = max(0, min(height - 1, round(int(region["y"]) / 10000 * height)))
    x2 = max(x1 + 1, min(width, round((int(region["x"]) + int(region["width"])) / 10000 * width)))
    y2 = max(y1 + 1, min(height, round((int(region["y"]) + int(region["height"])) / 10000 * height)))
    output = image[y1:y2, x1:x2].copy()
    rotation = int(region.get("rotation_degrees", 0))
    if rotation:
        center = (output.shape[1] / 2, output.shape[0] / 2)
        # Review decisions use the production convention: positive is clockwise.
        # OpenCV uses positive counter-clockwise angles.
        matrix = cv2.getRotationMatrix2D(center, -rotation, 1.0)
        cosine, sine = abs(matrix[0, 0]), abs(matrix[0, 1])
        target_width = round(output.shape[0] * sine + output.shape[1] * cosine)
        target_height = round(output.shape[0] * cosine + output.shape[1] * sine)
        matrix[0, 2] += target_width / 2 - center[0]
        matrix[1, 2] += target_height / 2 - center[1]
        output = cv2.warpAffine(output, matrix, (target_width, target_height), borderValue=(238, 238, 238))
    return output


def fit(image: np.ndarray, width: int, height: int) -> np.ndarray:
    scale = min(width / image.shape[1], height / image.shape[0])
    return cv2.resize(
        image,
        (max(1, round(image.shape[1] * scale)), max(1, round(image.shape[0] * scale))),
        interpolation=cv2.INTER_AREA,
    )


def render(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    decisions = [record for record in read_jsonl(arguments.decisions) if record.get("decision") == "multi"]
    arguments.output.mkdir(parents=True, exist_ok=True)
    manifest: list[dict[str, Any]] = []
    rows_per_page = max(1, min(10, arguments.rows_per_page))
    for page_index in range((len(decisions) + rows_per_page - 1) // rows_per_page):
        page = decisions[page_index * rows_per_page : (page_index + 1) * rows_per_page]
        canvas = np.full((len(page) * 230, 1500, 3), 245, dtype=np.uint8)
        item_ids: list[int] = []
        for row_index, decision in enumerate(page):
            item_id = int(decision["item_id"])
            item_ids.append(item_id)
            record = census.get(item_id)
            if record is None or decision["census_thumbnail_sha256"] != record["thumbnail_sha256"]:
                raise RuntimeError(f"Decision evidence does not match census item {item_id}")
            image_path = arguments.thumbnails / f"{item_id}.webp"
            image = cv2.imread(str(image_path), cv2.IMREAD_COLOR)
            if image is None:
                raise RuntimeError(f"Thumbnail missing for item {item_id}")
            source = image.copy()
            for region in decision["regions"]:
                x1 = round(int(region["x"]) / 10000 * source.shape[1])
                y1 = round(int(region["y"]) / 10000 * source.shape[0])
                x2 = round((int(region["x"]) + int(region["width"])) / 10000 * source.shape[1])
                y2 = round((int(region["y"]) + int(region["height"])) / 10000 * source.shape[0])
                cv2.rectangle(source, (x1, y1), (x2, y2), (30, 220, 30), 2)
            source = fit(source, 300, 190)
            top = row_index * 230 + 32
            canvas[top : top + source.shape[0], 8 : 8 + source.shape[1]] = source
            cv2.putText(canvas, f"item {item_id} ({len(decision['regions'])} crops)", (8, row_index * 230 + 23), cv2.FONT_HERSHEY_SIMPLEX, 0.58, (20, 20, 20), 1, cv2.LINE_AA)
            available_width = 1170
            cell_width = max(100, min(280, available_width // max(1, len(decision["regions"]))))
            left = 320
            for position, region in enumerate(decision["regions"], 1):
                output = fit(crop(image, region), cell_width - 8, 180)
                canvas[top : top + output.shape[0], left : left + output.shape[1]] = output
                cv2.putText(canvas, str(position), (left, row_index * 230 + 218), cv2.FONT_HERSHEY_SIMPLEX, 0.48, (20, 20, 20), 1, cv2.LINE_AA)
                left += cell_width
        page_path = arguments.output / f"crop-audit-{page_index + 1:05d}.jpg"
        cv2.imwrite(str(page_path), canvas, [cv2.IMWRITE_JPEG_QUALITY, 90])
        manifest.append(
            {
                "page": page_path.name,
                "page_sha256": file_sha256(page_path),
                "item_ids": item_ids,
                "decision_digests": {str(int(record["item_id"])): decision_digest(record) for record in page},
            }
        )
    write_jsonl(arguments.manifest, manifest)
    print(json.dumps({"multi_source_count": len(decisions), "page_count": len(manifest)}, separators=(",", ":")))
    return 0


def decide(arguments: argparse.Namespace) -> int:
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    audits = {int(record["item_id"]): record for record in read_jsonl(arguments.audit)}
    item_ids = [int(value) for value in arguments.item_ids.split(",") if value.strip()]
    for item_id in item_ids:
        decision = decisions.get(item_id)
        if decision is None or decision.get("decision") != "multi":
            raise ValueError(f"Item {item_id} is not a reviewed multi-photo decision")
        audits[item_id] = {
            "item_id": item_id,
            "result": arguments.result,
            "decision_digest": decision_digest(decision),
            "reviewed_at": datetime.now(timezone.utc).isoformat(),
            "reviewer": "codex_crop_visual_audit",
            "evidence": arguments.evidence,
            "note": arguments.note,
        }
    write_jsonl(arguments.audit, list(audits.values()))
    print(json.dumps({"recorded": len(item_ids), "result": arguments.result}, separators=(",", ":")))
    return 0


def decide_page(arguments: argparse.Namespace) -> int:
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    pages = {record["page"]: record for record in read_jsonl(arguments.manifest)}
    page = pages.get(arguments.page)
    if page is None:
        raise ValueError("The crop-audit page is not present in the current manifest")
    page_path = arguments.sheets / arguments.page
    if not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
        raise ValueError("The crop-audit page bytes do not match the current manifest")

    item_ids = [int(item_id) for item_id in page.get("item_ids", [])]
    if not item_ids or len(item_ids) != len(set(item_ids)):
        raise ValueError("The crop-audit page has no items or contains duplicate item IDs")

    expected_digests = page.get("decision_digests", {})
    prepared: dict[int, dict[str, Any]] = {}
    reviewed_at = datetime.now(timezone.utc).isoformat()
    for item_id in item_ids:
        decision = decisions.get(item_id)
        if decision is None or decision.get("decision") != "multi":
            raise ValueError(f"Item {item_id} is not a reviewed multi-photo decision")
        current_digest = decision_digest(decision)
        if expected_digests.get(str(item_id)) != current_digest:
            raise ValueError(f"Item {item_id} no longer matches the rendered crop-audit page")
        prepared[item_id] = {
            "item_id": item_id,
            "result": arguments.result,
            "decision_digest": current_digest,
            "reviewed_at": reviewed_at,
            "reviewer": "codex_crop_visual_audit",
            "evidence": arguments.page,
            "note": arguments.note,
            "audit_page": arguments.page,
            "audit_page_sha256": page["page_sha256"],
            "audit_page_digest": audit_page_digest(page),
        }

    audits = {int(record["item_id"]): record for record in read_jsonl(arguments.audit)}
    audits.update(prepared)
    write_jsonl(arguments.audit, list(audits.values()))
    print(
        json.dumps(
            {"page": arguments.page, "recorded": len(prepared), "result": arguments.result},
            separators=(",", ":"),
        )
    )
    return 0


def summary(arguments: argparse.Namespace) -> int:
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions) if record.get("decision") == "multi"}
    audits = {int(record["item_id"]): record for record in read_jsonl(arguments.audit)}
    pages = read_jsonl(arguments.manifest)
    bindings: dict[int, dict[str, Any]] = {}
    duplicate_manifest_item_ids: set[int] = set()
    page_byte_mismatches: list[str] = []
    for page in pages:
        page_name = str(page.get("page", ""))
        page_path = arguments.sheets / page_name
        if not page_name or not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
            page_byte_mismatches.append(page_name)
        binding = {
            "page": page_name,
            "page_sha256": page.get("page_sha256"),
            "page_digest": audit_page_digest(page),
            "decision_digests": page.get("decision_digests", {}),
        }
        for raw_item_id in page.get("item_ids", []):
            item_id = int(raw_item_id)
            if item_id in bindings:
                duplicate_manifest_item_ids.add(item_id)
            bindings[item_id] = binding

    current: dict[int, dict[str, Any]] = {}
    stale_audit_item_ids: list[int] = []
    for item_id, audit in audits.items():
        decision = decisions.get(item_id)
        binding = bindings.get(item_id)
        if decision is None or binding is None:
            stale_audit_item_ids.append(item_id)
            continue
        current_decision_digest = decision_digest(decision)
        if (
            audit.get("decision_digest") == current_decision_digest
            and binding["decision_digests"].get(str(item_id)) == current_decision_digest
            and audit.get("audit_page") == binding["page"]
            and audit.get("audit_page_sha256") == binding["page_sha256"]
            and audit.get("audit_page_digest") == binding["page_digest"]
            and binding["page"] not in page_byte_mismatches
        ):
            current[item_id] = audit
        else:
            stale_audit_item_ids.append(item_id)
    failed = sorted(item_id for item_id, audit in current.items() if audit.get("result") == "fail")
    pending = sorted(item_id for item_id in decisions if item_id not in current)
    output = {
        "multi_source_count": len(decisions),
        "passed_count": sum(audit.get("result") == "pass" for audit in current.values()),
        "failed_count": len(failed),
        "pending_count": len(pending),
        "manifest_source_count": len(bindings),
        "stale_audit_count": len(stale_audit_item_ids),
        "duplicate_manifest_item_count": len(duplicate_manifest_item_ids),
        "page_byte_mismatch_count": len(page_byte_mismatches),
        "failed_item_ids": failed[:100],
        "pending_item_ids": pending[:100],
        "stale_audit_item_ids": sorted(stale_audit_item_ids)[:100],
        "duplicate_manifest_item_ids": sorted(duplicate_manifest_item_ids)[:100],
        "page_byte_mismatches": page_byte_mismatches[:100],
    }
    print(json.dumps(output, separators=(",", ":")))
    return 0


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser()
    commands = result.add_subparsers(dest="command", required=True)
    render_command = commands.add_parser("render")
    render_command.add_argument("--census", type=Path, required=True)
    render_command.add_argument("--decisions", type=Path, required=True)
    render_command.add_argument("--thumbnails", type=Path, required=True)
    render_command.add_argument("--output", type=Path, required=True)
    render_command.add_argument("--manifest", type=Path, required=True)
    render_command.add_argument("--rows-per-page", type=int, default=6)
    render_command.set_defaults(handler=render)
    decide_command = commands.add_parser("decide")
    decide_command.add_argument("--decisions", type=Path, required=True)
    decide_command.add_argument("--audit", type=Path, required=True)
    decide_command.add_argument("--item-ids", required=True)
    decide_command.add_argument("--result", choices=["pass", "fail"], required=True)
    decide_command.add_argument("--evidence", required=True)
    decide_command.add_argument("--note", default="")
    decide_command.set_defaults(handler=decide)
    decide_page_command = commands.add_parser("decide-page")
    decide_page_command.add_argument("--decisions", type=Path, required=True)
    decide_page_command.add_argument("--audit", type=Path, required=True)
    decide_page_command.add_argument("--manifest", type=Path, required=True)
    decide_page_command.add_argument("--sheets", type=Path, required=True)
    decide_page_command.add_argument("--page", required=True)
    decide_page_command.add_argument("--result", choices=["pass", "fail"], required=True)
    decide_page_command.add_argument("--note", default="")
    decide_page_command.set_defaults(handler=decide_page)
    summary_command = commands.add_parser("summary")
    summary_command.add_argument("--decisions", type=Path, required=True)
    summary_command.add_argument("--audit", type=Path, required=True)
    summary_command.add_argument("--manifest", type=Path, required=True)
    summary_command.add_argument("--sheets", type=Path, required=True)
    summary_command.set_defaults(handler=summary)
    return result


if __name__ == "__main__":
    arguments = parser().parse_args()
    raise SystemExit(arguments.handler(arguments))
