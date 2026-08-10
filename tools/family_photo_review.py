#!/usr/bin/env python3
"""Manage durable visual decisions for the FamilyArchive split census."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import tempfile
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8") as stream:
        return [json.loads(line) for line in stream if line.strip()]


def write_jsonl(path: Path, records: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    handle, temporary = tempfile.mkstemp(prefix=path.name + ".", suffix=".tmp", dir=path.parent)
    try:
        with os.fdopen(handle, "w", encoding="utf-8", newline="\n") as stream:
            for record in sorted(
                records,
                key=lambda value: (0, int(value["item_id"]))
                if "item_id" in value
                else (1, str(value.get("page", ""))),
            ):
                stream.write(json.dumps(record, sort_keys=True, separators=(",", ":")) + "\n")
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def validate_regions(regions: Any) -> list[dict[str, Any]]:
    if not isinstance(regions, list) or not 2 <= len(regions) <= 32:
        raise ValueError("A multi-photo decision requires between 2 and 32 regions")
    validated: list[dict[str, Any]] = []
    for region in regions:
        if not isinstance(region, dict):
            raise ValueError("Each region must be an object")
        normalized = {
            "x": int(region["x"]),
            "y": int(region["y"]),
            "width": int(region["width"]),
            "height": int(region["height"]),
            "rotation_degrees": int(region.get("rotation_degrees", 0)),
            "included": bool(region.get("included", True)),
        }
        if normalized["x"] < 0 or normalized["y"] < 0 or normalized["width"] < 1 or normalized["height"] < 1:
            raise ValueError("Region bounds must be positive")
        if normalized["x"] + normalized["width"] > 10000 or normalized["y"] + normalized["height"] > 10000:
            raise ValueError("Region bounds exceed the source")
        validated.append(normalized)
    return validated


def command_decide(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    if arguments.item_id not in census:
        raise ValueError("The item is not present in the census ledger")
    existing = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    census_record = census[arguments.item_id]
    regions: list[dict[str, Any]] = []
    if arguments.decision == "multi":
        if arguments.regions_json:
            regions = validate_regions(json.loads(arguments.regions_json))
        else:
            regions = validate_regions(census_record.get("regions"))
    existing[arguments.item_id] = {
        "item_id": arguments.item_id,
        "decision": arguments.decision,
        "regions": regions,
        "reviewed_at": datetime.now(timezone.utc).isoformat(),
        "reviewer": "codex_visual_review",
        "evidence": arguments.evidence,
        "note": arguments.note,
        "census_thumbnail_sha256": census_record["thumbnail_sha256"],
    }
    write_jsonl(arguments.decisions, list(existing.values()))
    print(json.dumps(existing[arguments.item_id], separators=(",", ":")))
    return 0


def command_summary(arguments: argparse.Namespace) -> int:
    census = read_jsonl(arguments.census)
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    auto_single = sum(record["classification"] == "single_high" for record in census)
    pending = [record for record in census if record["classification"] != "single_high" and int(record["item_id"]) not in decisions]
    summary = {
        "census_count": len(census),
        "automatic_single_count": auto_single,
        "visual_decision_count": len(decisions),
        "pending_visual_count": len(pending),
        "pending_item_ids": [int(record["item_id"]) for record in pending[:100]],
    }
    print(json.dumps(summary, separators=(",", ":")))
    return 0


def command_materialize_automatic(arguments: argparse.Namespace) -> int:
    census = read_jsonl(arguments.census)
    existing = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    added = 0
    for record in census:
        item_id = int(record["item_id"])
        if record["classification"] != "single_high" or item_id in existing:
            continue
        existing[item_id] = {
            "item_id": item_id,
            "decision": "single",
            "regions": [],
            "reviewed_at": datetime.now(timezone.utc).isoformat(),
            "reviewer": "automatic_confident_single",
            "evidence": "family_photo_census_engine_v2",
            "note": "High-confidence single-photo geometry; no split regions detected.",
            "census_thumbnail_sha256": record["thumbnail_sha256"],
        }
        added += 1
    write_jsonl(arguments.decisions, list(existing.values()))
    print(json.dumps({"added": added, "total_decisions": len(existing)}, separators=(",", ":")))
    return 0


def audit_page_digest(page: dict[str, Any]) -> str:
    return hashlib.sha256(json.dumps(page, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def command_audit_page(arguments: argparse.Namespace) -> int:
    manifest = {record["page"]: record for record in read_jsonl(arguments.audit_manifest)}
    if arguments.page not in manifest:
        raise ValueError("The audit page is not present in the deterministic manifest")
    page = manifest[arguments.page]
    page_ids = {int(item["item_id"]) for item in page["items"]}
    false_negatives = {
        int(value) for value in arguments.false_negative_item_ids.split(",") if value.strip()
    }
    if not false_negatives.issubset(page_ids):
        raise ValueError("A false-negative item is not present on the audited page")
    existing = {record["page"]: record for record in read_jsonl(arguments.audit_ledger)}
    existing[arguments.page] = {
        "page": arguments.page,
        "page_digest": audit_page_digest(page),
        "reviewed_at": datetime.now(timezone.utc).isoformat(),
        "reviewer": "codex_automatic_single_visual_audit",
        "false_negative_item_ids": sorted(false_negatives),
        "note": arguments.note,
    }
    write_jsonl(arguments.audit_ledger, list(existing.values()))
    print(json.dumps(existing[arguments.page], separators=(",", ":")))
    return 0


def command_audit_summary(arguments: argparse.Namespace) -> int:
    pages = {record["page"]: record for record in read_jsonl(arguments.audit_manifest)}
    audits = {record["page"]: record for record in read_jsonl(arguments.audit_ledger)}
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    current = {
        page_name: audit
        for page_name, audit in audits.items()
        if page_name in pages and audit.get("page_digest") == audit_page_digest(pages[page_name])
    }
    false_negatives = sorted(
        {
            int(item_id)
            for audit in current.values()
            for item_id in audit.get("false_negative_item_ids", [])
        }
    )
    unresolved = [
        item_id
        for item_id in false_negatives
        if decisions.get(item_id, {}).get("decision") != "multi"
    ]
    reviewed_items = sum(len(pages[page_name]["items"]) for page_name in current)
    output = {
        "page_count": len(pages),
        "reviewed_page_count": len(current),
        "sampled_source_count": sum(len(page["items"]) for page in pages.values()),
        "reviewed_source_count": reviewed_items,
        "false_negative_count": len(false_negatives),
        "unresolved_false_negative_count": len(unresolved),
        "pending_pages": sorted(set(pages) - set(current)),
        "unresolved_item_ids": unresolved[:100],
    }
    print(json.dumps(output, separators=(",", ":")))
    return 0


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser()
    subcommands = result.add_subparsers(dest="command", required=True)
    decide = subcommands.add_parser("decide")
    decide.add_argument("--census", type=Path, required=True)
    decide.add_argument("--decisions", type=Path, required=True)
    decide.add_argument("--item-id", type=int, required=True)
    decide.add_argument("--decision", choices=["single", "multi", "exclude"], required=True)
    decide.add_argument("--regions-json")
    decide.add_argument("--evidence", required=True)
    decide.add_argument("--note", default="")
    decide.set_defaults(handler=command_decide)
    summary = subcommands.add_parser("summary")
    summary.add_argument("--census", type=Path, required=True)
    summary.add_argument("--decisions", type=Path, required=True)
    summary.set_defaults(handler=command_summary)
    automatic = subcommands.add_parser("materialize-automatic")
    automatic.add_argument("--census", type=Path, required=True)
    automatic.add_argument("--decisions", type=Path, required=True)
    automatic.set_defaults(handler=command_materialize_automatic)
    audit_page = subcommands.add_parser("audit-page")
    audit_page.add_argument("--audit-manifest", type=Path, required=True)
    audit_page.add_argument("--audit-ledger", type=Path, required=True)
    audit_page.add_argument("--page", required=True)
    audit_page.add_argument("--false-negative-item-ids", default="")
    audit_page.add_argument("--note", default="")
    audit_page.set_defaults(handler=command_audit_page)
    audit_summary = subcommands.add_parser("audit-summary")
    audit_summary.add_argument("--audit-manifest", type=Path, required=True)
    audit_summary.add_argument("--audit-ledger", type=Path, required=True)
    audit_summary.add_argument("--decisions", type=Path, required=True)
    audit_summary.set_defaults(handler=command_audit_summary)
    return result


def main() -> int:
    arguments = parser().parse_args()
    return arguments.handler(arguments)


if __name__ == "__main__":
    raise SystemExit(main())
