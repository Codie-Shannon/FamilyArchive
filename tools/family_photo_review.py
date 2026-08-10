#!/usr/bin/env python3
"""Manage durable visual decisions for the FamilyArchive split census."""

from __future__ import annotations

import argparse
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
            for record in sorted(records, key=lambda value: int(value["item_id"])):
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
    return result


def main() -> int:
    arguments = parser().parse_args()
    return arguments.handler(arguments)


if __name__ == "__main__":
    raise SystemExit(main())
