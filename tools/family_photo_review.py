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


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def review_proposal_digest(record: dict[str, Any]) -> str:
    payload = {
        "item_id": int(record["item_id"]),
        "classification": record["classification"],
        "regions": record.get("regions", []),
        "thumbnail_sha256": record["thumbnail_sha256"],
        "engine_version": record["engine_version"],
    }
    return hashlib.sha256(json.dumps(payload, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def review_page_digest(page: dict[str, Any]) -> str:
    return hashlib.sha256(json.dumps(page, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


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


def command_batch_decide(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    submitted = read_jsonl(arguments.input)
    if not submitted:
        raise ValueError("The batch decision input is empty")

    item_ids = [int(record["item_id"]) for record in submitted]
    if len(item_ids) != len(set(item_ids)):
        raise ValueError("The batch decision input contains duplicate item IDs")

    prepared: dict[int, dict[str, Any]] = {}
    reviewed_at = datetime.now(timezone.utc).isoformat()
    for record in submitted:
        item_id = int(record["item_id"])
        if item_id not in census:
            raise ValueError(f"Item {item_id} is not present in the census ledger")

        census_record = census[item_id]
        expected_thumbnail_sha256 = str(record.get("census_thumbnail_sha256", ""))
        if expected_thumbnail_sha256 != census_record["thumbnail_sha256"]:
            raise ValueError(f"Item {item_id} is not bound to the current census thumbnail")

        decision = str(record.get("decision", ""))
        if decision not in {"single", "multi", "exclude"}:
            raise ValueError(f"Item {item_id} has an unsupported decision")
        evidence = str(record.get("evidence", "")).strip()
        if not evidence:
            raise ValueError(f"Item {item_id} is missing visual-review evidence")

        regions: list[dict[str, Any]] = []
        if decision == "multi":
            regions = validate_regions(record.get("regions", census_record.get("regions")))
        elif record.get("regions"):
            raise ValueError(f"Item {item_id} supplies regions for a non-multi decision")

        prepared[item_id] = {
            "item_id": item_id,
            "decision": decision,
            "regions": regions,
            "reviewed_at": reviewed_at,
            "reviewer": "codex_visual_review",
            "evidence": evidence,
            "note": str(record.get("note", "")),
            "census_thumbnail_sha256": census_record["thumbnail_sha256"],
        }

    existing = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    existing.update(prepared)
    write_jsonl(arguments.decisions, list(existing.values()))
    print(
        json.dumps(
            {
                "applied": len(prepared),
                "total_decisions": len(existing),
                "first_item_id": min(prepared),
                "last_item_id": max(prepared),
            },
            separators=(",", ":"),
        )
    )
    return 0


def command_decide_page(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    pages = {record["page"]: record for record in read_jsonl(arguments.review_manifest)}
    page = pages.get(arguments.page)
    if page is None:
        raise ValueError("The visual-review page is not present in the current manifest")

    page_path = arguments.review_sheets / arguments.page
    if not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
        raise ValueError("The visual-review page bytes do not match the current manifest")

    page_items = {int(item["item_id"]): item for item in page.get("items", [])}
    submitted = read_jsonl(arguments.input)
    submitted_by_id = {int(record["item_id"]): record for record in submitted}
    if not page_items or len(submitted_by_id) != len(submitted):
        raise ValueError("The page decision input is empty or contains duplicate item IDs")
    if set(submitted_by_id) != set(page_items):
        raise ValueError("The page decision input must decide every item on exactly one review page")

    prepared: dict[int, dict[str, Any]] = {}
    reviewed_at = datetime.now(timezone.utc).isoformat()
    current_page_digest = review_page_digest(page)
    for item_id, manifest_item in page_items.items():
        census_record = census.get(item_id)
        if census_record is None:
            raise ValueError(f"Item {item_id} is not present in the census ledger")
        if (
            manifest_item.get("thumbnail_sha256") != census_record.get("thumbnail_sha256")
            or manifest_item.get("engine_version") != census_record.get("engine_version")
            or manifest_item.get("proposal_digest") != review_proposal_digest(census_record)
        ):
            raise ValueError(f"Item {item_id} no longer matches the rendered visual-review page")

        submitted_record = submitted_by_id[item_id]
        decision = str(submitted_record.get("decision", ""))
        if decision not in {"single", "multi", "exclude"}:
            raise ValueError(f"Item {item_id} has an unsupported decision")
        regions: list[dict[str, Any]] = []
        if decision == "multi":
            regions = validate_regions(submitted_record.get("regions", census_record.get("regions")))
        elif submitted_record.get("regions"):
            raise ValueError(f"Item {item_id} supplies regions for a non-multi decision")

        prepared[item_id] = {
            "item_id": item_id,
            "decision": decision,
            "regions": regions,
            "reviewed_at": reviewed_at,
            "reviewer": "codex_visual_review",
            "evidence": arguments.page,
            "note": str(submitted_record.get("note", "")),
            "census_thumbnail_sha256": census_record["thumbnail_sha256"],
            "review_page": arguments.page,
            "review_page_sha256": page["page_sha256"],
            "review_page_digest": current_page_digest,
            "review_proposal_digest": manifest_item["proposal_digest"],
        }

    existing = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    existing.update(prepared)
    write_jsonl(arguments.decisions, list(existing.values()))
    print(
        json.dumps(
            {"page": arguments.page, "applied": len(prepared), "total_decisions": len(existing)},
            separators=(",", ":"),
        )
    )
    return 0


def command_page_template(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    pages = {record["page"]: record for record in read_jsonl(arguments.review_manifest)}
    page = pages.get(arguments.page)
    if page is None:
        raise ValueError("The visual-review page is not present in the current manifest")
    page_path = arguments.review_sheets / arguments.page
    if not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
        raise ValueError("The visual-review page bytes do not match the current manifest")

    template: list[dict[str, Any]] = []
    for manifest_item in page.get("items", []):
        item_id = int(manifest_item["item_id"])
        census_record = census.get(item_id)
        if census_record is None or (
            manifest_item.get("thumbnail_sha256") != census_record.get("thumbnail_sha256")
            or manifest_item.get("engine_version") != census_record.get("engine_version")
            or manifest_item.get("proposal_digest") != review_proposal_digest(census_record)
        ):
            raise ValueError(f"Item {item_id} no longer matches the rendered visual-review page")
        proposed_regions = census_record.get("regions", [])
        template.append(
            {
                "item_id": item_id,
                "decision": "multi" if len(proposed_regions) >= 2 else "review_required",
                "regions": proposed_regions if len(proposed_regions) >= 2 else [],
                "note": "",
            }
        )

    write_jsonl(arguments.output, template)
    unresolved = sum(record["decision"] == "review_required" for record in template)
    print(
        json.dumps(
            {
                "page": arguments.page,
                "output": str(arguments.output),
                "item_count": len(template),
                "prefilled_multi_count": len(template) - unresolved,
                "review_required_count": unresolved,
            },
            separators=(",", ":"),
        )
    )
    return 0


def command_prepare_page_templates(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    pages = read_jsonl(arguments.review_manifest)
    arguments.output.mkdir(parents=True, exist_ok=True)
    generated: list[str] = []
    preserved: list[str] = []
    pending_pages: list[str] = []

    for page in pages:
        page_name = str(page.get("page", ""))
        page_path = arguments.review_sheets / page_name
        if not page_name or not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
            raise ValueError(f"The visual-review page bytes do not match the manifest: {page_name}")
        current_page_digest = review_page_digest(page)
        template: list[dict[str, Any]] = []
        page_is_current = True
        for manifest_item in page.get("items", []):
            item_id = int(manifest_item["item_id"])
            census_record = census.get(item_id)
            if census_record is None or (
                manifest_item.get("thumbnail_sha256") != census_record.get("thumbnail_sha256")
                or manifest_item.get("engine_version") != census_record.get("engine_version")
                or manifest_item.get("proposal_digest") != review_proposal_digest(census_record)
            ):
                raise ValueError(f"Item {item_id} no longer matches the rendered visual-review page")

            decision = decisions.get(item_id)
            decision_is_current = decision is not None and (
                decision.get("reviewer") == "codex_visual_review"
                and decision.get("review_page") == page_name
                and decision.get("review_page_sha256") == page.get("page_sha256")
                and decision.get("review_page_digest") == current_page_digest
                and decision.get("review_proposal_digest") == manifest_item.get("proposal_digest")
                and decision.get("census_thumbnail_sha256") == census_record.get("thumbnail_sha256")
                and decision.get("decision") in {"single", "multi", "exclude"}
            )
            page_is_current = page_is_current and decision_is_current
            if decision_is_current:
                template.append(
                    {
                        "item_id": item_id,
                        "decision": decision["decision"],
                        "regions": decision.get("regions", []),
                        "note": decision.get("note", ""),
                    }
                )
                continue

            proposed_regions = census_record.get("regions", [])
            template.append(
                {
                    "item_id": item_id,
                    "decision": "multi" if len(proposed_regions) >= 2 else "review_required",
                    "regions": proposed_regions if len(proposed_regions) >= 2 else [],
                    "note": "",
                }
            )

        if page_is_current:
            continue
        pending_pages.append(page_name)
        output_name = (
            f"{Path(page_name).stem}.{str(page['page_sha256'])[:12]}."
            f"{current_page_digest[:12]}.jsonl"
        )
        output_path = arguments.output / output_name
        if output_path.exists():
            preserved.append(str(output_path))
        else:
            write_jsonl(output_path, template)
            generated.append(str(output_path))

    print(
        json.dumps(
            {
                "page_count": len(pages),
                "pending_page_count": len(pending_pages),
                "generated_template_count": len(generated),
                "preserved_template_count": len(preserved),
                "pending_pages": pending_pages[:100],
                "generated_templates": generated[:100],
                "preserved_templates": preserved[:100],
            },
            separators=(",", ":"),
        )
    )
    return 0


def command_summary(arguments: argparse.Namespace) -> int:
    census = read_jsonl(arguments.census)
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    auto_single = sum(
        record["classification"] == "single_high" and not record.get("canonical_duplicate", False)
        for record in census
    )
    automatic_duplicates = sum(bool(record.get("canonical_duplicate", False)) for record in census)
    pending = [
        record
        for record in census
        if record.get("review_state") == "pending_visual_review" and int(record["item_id"]) not in decisions
    ]
    summary = {
        "census_count": len(census),
        "automatic_single_count": auto_single,
        "automatic_exact_duplicate_count": automatic_duplicates,
        "visual_decision_count": len(decisions),
        "pending_visual_count": len(pending),
        "pending_item_ids": [int(record["item_id"]) for record in pending[:100]],
    }
    print(json.dumps(summary, separators=(",", ":")))
    return 0


def command_review_evidence_summary(arguments: argparse.Namespace) -> int:
    census = {int(record["item_id"]): record for record in read_jsonl(arguments.census)}
    decisions = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    pages = read_jsonl(arguments.review_manifest)
    bindings: dict[int, dict[str, Any]] = {}
    duplicate_item_ids: set[int] = set()
    stale_manifest_item_ids: set[int] = set()
    page_byte_mismatches: list[str] = []

    for page in pages:
        page_name = str(page.get("page", ""))
        page_path = arguments.review_sheets / page_name
        if not page_name or not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
            page_byte_mismatches.append(page_name)
        current_page_digest = review_page_digest(page)
        for manifest_item in page.get("items", []):
            item_id = int(manifest_item["item_id"])
            if item_id in bindings:
                duplicate_item_ids.add(item_id)
            census_record = census.get(item_id)
            if census_record is None or (
                manifest_item.get("thumbnail_sha256") != census_record.get("thumbnail_sha256")
                or manifest_item.get("engine_version") != census_record.get("engine_version")
                or manifest_item.get("proposal_digest") != review_proposal_digest(census_record)
            ):
                stale_manifest_item_ids.add(item_id)
            bindings[item_id] = {
                "page": page_name,
                "page_sha256": page.get("page_sha256"),
                "page_digest": current_page_digest,
                "proposal_digest": manifest_item.get("proposal_digest"),
            }

    visual_item_ids = {
        item_id for item_id, record in census.items() if record.get("classification") != "single_high"
    }
    current_decision_ids: set[int] = set()
    stale_decision_ids: set[int] = set()
    for item_id in visual_item_ids:
        decision = decisions.get(item_id)
        binding = bindings.get(item_id)
        if decision is None or binding is None:
            continue
        if (
            decision.get("reviewer") == "codex_visual_review"
            and decision.get("review_page") == binding["page"]
            and decision.get("review_page_sha256") == binding["page_sha256"]
            and decision.get("review_page_digest") == binding["page_digest"]
            and decision.get("review_proposal_digest") == binding["proposal_digest"]
            and decision.get("census_thumbnail_sha256") == census[item_id].get("thumbnail_sha256")
            and decision.get("decision") in {"single", "multi", "exclude"}
        ):
            current_decision_ids.add(item_id)
        else:
            stale_decision_ids.add(item_id)

    unresolved_item_ids = visual_item_ids - current_decision_ids
    pending_pages = [
        page["page"]
        for page in pages
        if any(int(item["item_id"]) in unresolved_item_ids for item in page.get("items", []))
    ]
    output = {
        "page_count": len(pages),
        "visual_source_count": len(visual_item_ids),
        "manifest_source_count": len(bindings),
        "current_decision_count": len(current_decision_ids),
        "pending_decision_count": len(visual_item_ids - current_decision_ids - stale_decision_ids),
        "stale_decision_count": len(stale_decision_ids),
        "duplicate_manifest_item_count": len(duplicate_item_ids),
        "stale_manifest_item_count": len(stale_manifest_item_ids),
        "page_byte_mismatch_count": len(page_byte_mismatches),
        "pending_page_count": len(pending_pages),
        "pending_item_ids": sorted(visual_item_ids - current_decision_ids - stale_decision_ids)[:100],
        "stale_decision_item_ids": sorted(stale_decision_ids)[:100],
        "duplicate_manifest_item_ids": sorted(duplicate_item_ids)[:100],
        "stale_manifest_item_ids": sorted(stale_manifest_item_ids)[:100],
        "page_byte_mismatches": page_byte_mismatches[:100],
        "pending_pages": pending_pages[:100],
    }
    print(json.dumps(output, separators=(",", ":")))
    return 0


def command_materialize_automatic(arguments: argparse.Namespace) -> int:
    census = read_jsonl(arguments.census)
    existing = {int(record["item_id"]): record for record in read_jsonl(arguments.decisions)}
    added = 0
    for record in census:
        item_id = int(record["item_id"])
        if item_id in existing:
            continue
        if record.get("canonical_duplicate", False):
            existing[item_id] = {
                "item_id": item_id,
                "decision": "exclude",
                "regions": [],
                "reviewed_at": datetime.now(timezone.utc).isoformat(),
                "reviewer": "automatic_exact_duplicate",
                "evidence": "retained_sha256_matches_canonical_original",
                "note": "Exact retained-byte duplicate is represented by its canonical archive original.",
                "census_thumbnail_sha256": record["thumbnail_sha256"],
            }
            added += 1
            continue
        if record["classification"] != "single_high":
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
    page_path = arguments.audit_sheets / arguments.page
    if not page_path.is_file() or file_sha256(page_path) != page.get("page_sha256"):
        raise ValueError("The automatic-single audit page bytes do not match the manifest")
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
    page_byte_mismatches = {
        page_name
        for page_name, page in pages.items()
        if not (arguments.audit_sheets / page_name).is_file()
        or file_sha256(arguments.audit_sheets / page_name) != page.get("page_sha256")
    }
    current = {
        page_name: audit
        for page_name, audit in audits.items()
        if page_name in pages
        and page_name not in page_byte_mismatches
        and audit.get("page_digest") == audit_page_digest(pages[page_name])
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
        "page_byte_mismatch_count": len(page_byte_mismatches),
        "pending_pages": sorted(set(pages) - set(current)),
        "page_byte_mismatches": sorted(page_byte_mismatches),
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
    batch_decide = subcommands.add_parser("batch-decide")
    batch_decide.add_argument("--census", type=Path, required=True)
    batch_decide.add_argument("--decisions", type=Path, required=True)
    batch_decide.add_argument("--input", type=Path, required=True)
    batch_decide.set_defaults(handler=command_batch_decide)
    decide_page = subcommands.add_parser("decide-page")
    decide_page.add_argument("--census", type=Path, required=True)
    decide_page.add_argument("--decisions", type=Path, required=True)
    decide_page.add_argument("--review-manifest", type=Path, required=True)
    decide_page.add_argument("--review-sheets", type=Path, required=True)
    decide_page.add_argument("--page", required=True)
    decide_page.add_argument("--input", type=Path, required=True)
    decide_page.set_defaults(handler=command_decide_page)
    page_template = subcommands.add_parser("page-template")
    page_template.add_argument("--census", type=Path, required=True)
    page_template.add_argument("--review-manifest", type=Path, required=True)
    page_template.add_argument("--review-sheets", type=Path, required=True)
    page_template.add_argument("--page", required=True)
    page_template.add_argument("--output", type=Path, required=True)
    page_template.set_defaults(handler=command_page_template)
    prepare_templates = subcommands.add_parser("prepare-page-templates")
    prepare_templates.add_argument("--census", type=Path, required=True)
    prepare_templates.add_argument("--decisions", type=Path, required=True)
    prepare_templates.add_argument("--review-manifest", type=Path, required=True)
    prepare_templates.add_argument("--review-sheets", type=Path, required=True)
    prepare_templates.add_argument("--output", type=Path, required=True)
    prepare_templates.set_defaults(handler=command_prepare_page_templates)
    summary = subcommands.add_parser("summary")
    summary.add_argument("--census", type=Path, required=True)
    summary.add_argument("--decisions", type=Path, required=True)
    summary.set_defaults(handler=command_summary)
    review_evidence = subcommands.add_parser("review-evidence-summary")
    review_evidence.add_argument("--census", type=Path, required=True)
    review_evidence.add_argument("--decisions", type=Path, required=True)
    review_evidence.add_argument("--review-manifest", type=Path, required=True)
    review_evidence.add_argument("--review-sheets", type=Path, required=True)
    review_evidence.set_defaults(handler=command_review_evidence_summary)
    automatic = subcommands.add_parser("materialize-automatic")
    automatic.add_argument("--census", type=Path, required=True)
    automatic.add_argument("--decisions", type=Path, required=True)
    automatic.set_defaults(handler=command_materialize_automatic)
    audit_page = subcommands.add_parser("audit-page")
    audit_page.add_argument("--audit-manifest", type=Path, required=True)
    audit_page.add_argument("--audit-ledger", type=Path, required=True)
    audit_page.add_argument("--audit-sheets", type=Path, required=True)
    audit_page.add_argument("--page", required=True)
    audit_page.add_argument("--false-negative-item-ids", default="")
    audit_page.add_argument("--note", default="")
    audit_page.set_defaults(handler=command_audit_page)
    audit_summary = subcommands.add_parser("audit-summary")
    audit_summary.add_argument("--audit-manifest", type=Path, required=True)
    audit_summary.add_argument("--audit-ledger", type=Path, required=True)
    audit_summary.add_argument("--audit-sheets", type=Path, required=True)
    audit_summary.add_argument("--decisions", type=Path, required=True)
    audit_summary.set_defaults(handler=command_audit_summary)
    return result


def main() -> int:
    arguments = parser().parse_args()
    return arguments.handler(arguments)


if __name__ == "__main__":
    raise SystemExit(main())
