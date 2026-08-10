#!/usr/bin/env python3
"""Durable, privacy-local census for FamilyArchive scanner sheets.

The script reads cached analysis thumbnails and a JSONL manifest, records one
deterministic decision per source, reuses recurring layouts only when each
image independently supports the template edges, and produces bounded contact
sheets for the remaining visual-review queue.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import os
import sys
import tempfile
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable

LOCAL_DEPENDENCIES = Path(__file__).resolve().parents[1] / "output" / "family-photo-python-libs"
if LOCAL_DEPENDENCIES.is_dir():
    sys.path.insert(0, str(LOCAL_DEPENDENCIES))

import cv2
import numpy as np

ENGINE_VERSION = 4


@dataclass(frozen=True)
class Region:
    x: int
    y: int
    width: int
    height: int
    rotation_degrees: int = 0
    confidence: float = 0.0

    def basis_points(self, image_width: int, image_height: int) -> dict[str, Any]:
        x = max(0, min(9999, round((self.x / image_width) * 10000)))
        y = max(0, min(9999, round((self.y / image_height) * 10000)))
        right = max(x + 1, min(10000, round(((self.x + self.width) / image_width) * 10000)))
        bottom = max(y + 1, min(10000, round(((self.y + self.height) / image_height) * 10000)))
        return {
            "x": x,
            "y": y,
            "width": right - x,
            "height": bottom - y,
            "rotation_degrees": self.rotation_degrees,
            "included": True,
            "confidence": round(self.confidence, 4),
        }


def atomic_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    handle, temporary = tempfile.mkstemp(prefix=path.name + ".", suffix=".tmp", dir=path.parent)
    try:
        with os.fdopen(handle, "w", encoding="utf-8", newline="\n") as stream:
            json.dump(payload, stream, indent=2, sort_keys=True)
            stream.write("\n")
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []
    if not path.exists():
        return records
    with path.open("r", encoding="utf-8") as stream:
        for line in stream:
            if line.strip():
                records.append(json.loads(line))
    return records


def image_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def intersection_over_union(left: Region, right: Region) -> float:
    x1 = max(left.x, right.x)
    y1 = max(left.y, right.y)
    x2 = min(left.x + left.width, right.x + right.width)
    y2 = min(left.y + left.height, right.y + right.height)
    intersection = max(0, x2 - x1) * max(0, y2 - y1)
    if intersection == 0:
        return 0.0
    union = left.width * left.height + right.width * right.height - intersection
    return intersection / max(1, union)


def nms(regions: Iterable[Region], maximum_iou: float = 0.30) -> list[Region]:
    selected: list[Region] = []
    for candidate in sorted(regions, key=lambda item: item.confidence * item.width * item.height, reverse=True):
        if all(intersection_over_union(candidate, existing) <= maximum_iou for existing in selected):
            selected.append(candidate)
    return sorted(selected, key=lambda item: (item.y, item.x))


def strongest_seam(channel: np.ndarray, minimum_support: float = 0.62) -> tuple[int, float] | None:
    """Return a full-span discontinuity while rejecting local scene edges."""
    length = channel.shape[1]
    if length < 80:
        return None
    differences = np.linalg.norm(channel[:, 1:, :] - channel[:, :-1, :], axis=2)
    scores = np.mean(differences, axis=0)
    median = float(np.median(scores))
    mad = float(np.median(np.abs(scores - median)))
    start = max(12, round(length * 0.12))
    end = min(length - 12, round(length * 0.88))
    if end <= start:
        return None
    candidates = np.argsort(scores[start:end])[::-1] + start
    for position in candidates[: min(20, len(candidates))]:
        score = float(scores[position])
        support = float(np.mean(differences[:, position] >= max(16.0, median + 2.5 * mad)))
        robust_threshold = median + max(7.5, 5.0 * mad)
        if score >= robust_threshold and support >= minimum_support:
            return int(position + 1), round(min(0.99, 0.55 + support * 0.38), 4)
    return None


def seam_regions(image: np.ndarray, maximum_regions: int = 16) -> tuple[list[Region], dict[str, Any]]:
    """Recursively split aligned scan/collage tiles on full-span image seams."""
    height, width = image.shape[:2]
    lab = cv2.cvtColor(image, cv2.COLOR_BGR2LAB).astype(np.float32)
    pending: list[tuple[int, int, int, int, int]] = [(0, 0, width, height, 0)]
    leaves: list[tuple[int, int, int, int]] = []
    seam_confidences: list[float] = []
    while pending and len(pending) + len(leaves) < maximum_regions:
        x, y, region_width, region_height, depth = pending.pop()
        if depth >= 4 or region_width < width * 0.20 or region_height < height * 0.20:
            leaves.append((x, y, region_width, region_height))
            continue
        crop = lab[y : y + region_height, x : x + region_width]
        vertical = strongest_seam(crop)
        horizontal_raw = strongest_seam(np.transpose(crop, (1, 0, 2)))
        horizontal = None if horizontal_raw is None else (horizontal_raw[0], horizontal_raw[1])
        choices: list[tuple[str, int, float]] = []
        if vertical is not None:
            choices.append(("vertical", vertical[0], vertical[1]))
        if horizontal is not None:
            choices.append(("horizontal", horizontal[0], horizontal[1]))
        if not choices:
            leaves.append((x, y, region_width, region_height))
            continue
        axis, position, confidence = max(choices, key=lambda value: value[2])
        if axis == "vertical":
            first = (x, y, position, region_height, depth + 1)
            second = (x + position, y, region_width - position, region_height, depth + 1)
        else:
            first = (x, y, region_width, position, depth + 1)
            second = (x, y + position, region_width, region_height - position, depth + 1)
        minimum_area = width * height * 0.045
        if first[2] * first[3] < minimum_area or second[2] * second[3] < minimum_area:
            leaves.append((x, y, region_width, region_height))
            continue
        seam_confidences.append(confidence)
        pending.extend([second, first])

    leaves.extend((x, y, w, h) for x, y, w, h, _ in pending)
    leaves = [
        leaf
        for leaf in leaves
        if leaf[2] >= width * 0.12 and leaf[3] >= height * 0.12 and leaf[2] * leaf[3] >= width * height * 0.045
    ]
    informative_leaves: list[tuple[int, int, int, int]] = []
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    for leaf in leaves:
        x, y, leaf_width, leaf_height = leaf
        crop = gray[y : y + leaf_height, x : x + leaf_width]
        edge_density = float(np.count_nonzero(cv2.Canny(crop, 45, 135))) / max(1, crop.size)
        if float(np.std(crop)) >= 10.0 or edge_density >= 0.012:
            informative_leaves.append(leaf)
    leaves = informative_leaves
    if len(leaves) < 2:
        return [], {"seam_count": 0}
    inset = max(1, round(min(width, height) * 0.003))
    regions = [
        Region(
            min(width - 1, x + inset),
            min(height - 1, y + inset),
            max(1, region_width - 2 * inset),
            max(1, region_height - 2 * inset),
            0,
            min(seam_confidences) if seam_confidences else 0.0,
        )
        for x, y, region_width, region_height in sorted(leaves, key=lambda value: (value[1], value[0]))
    ]
    return regions, {
        "seam_count": len(seam_confidences),
        "seam_minimum_confidence": round(min(seam_confidences), 4) if seam_confidences else 0.0,
    }


def contour_regions(image: np.ndarray) -> tuple[list[Region], dict[str, float]]:
    height, width = image.shape[:2]
    lab = cv2.cvtColor(image, cv2.COLOR_BGR2LAB).astype(np.float32)
    border = max(2, min(width, height) // 45)
    border_pixels = np.concatenate(
        [
            lab[:border, :, :].reshape(-1, 3),
            lab[-border:, :, :].reshape(-1, 3),
            lab[:, :border, :].reshape(-1, 3),
            lab[:, -border:, :].reshape(-1, 3),
        ]
    )
    background = np.median(border_pixels, axis=0)
    distances = np.linalg.norm(lab - background, axis=2)
    border_distances = np.linalg.norm(border_pixels - background, axis=1)
    border_median = float(np.median(border_distances))
    border_mad = float(np.median(np.abs(border_distances - border_median)))
    threshold = float(max(13.0, border_median + 3.5 * border_mad + 9.0))
    mask = np.where(distances >= threshold, 255, 0).astype(np.uint8)
    kernel_size = max(3, int(round(min(width, height) * 0.012)) | 1)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (kernel_size, kernel_size))
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel, iterations=2)
    mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel, iterations=1)

    candidates: list[Region] = []
    image_area = width * height
    contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    for contour in contours:
        area = float(cv2.contourArea(contour))
        area_ratio = area / max(1, image_area)
        if area_ratio < 0.025 or area_ratio > 0.82:
            continue
        x, y, region_width, region_height = cv2.boundingRect(contour)
        if region_width < width * 0.12 or region_height < height * 0.12:
            continue
        rectangularity = area / max(1.0, region_width * region_height)
        if rectangularity < 0.63:
            continue
        rotated = cv2.minAreaRect(contour)
        angle = float(rotated[2])
        rotated_width, rotated_height = rotated[1]
        if rotated_width < rotated_height:
            angle += 90.0
        angle = ((angle + 45.0) % 90.0) - 45.0
        rotation = int(round(-angle)) if abs(angle) <= 12 else 0
        confidence = min(0.99, 0.58 + 0.30 * rectangularity + 0.12 * min(1.0, area_ratio / 0.18))
        candidates.append(Region(x, y, region_width, region_height, rotation, confidence))

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    edges = cv2.Canny(gray, 45, 135)
    edge_kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
    edges = cv2.dilate(edges, edge_kernel, iterations=1)
    edge_contours, _ = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    for contour in edge_contours:
        perimeter = cv2.arcLength(contour, True)
        polygon = cv2.approxPolyDP(contour, 0.025 * perimeter, True)
        if len(polygon) != 4 or not cv2.isContourConvex(polygon):
            continue
        area = float(abs(cv2.contourArea(polygon)))
        area_ratio = area / max(1, image_area)
        if area_ratio < 0.035 or area_ratio > 0.78:
            continue
        x, y, region_width, region_height = cv2.boundingRect(polygon)
        rectangularity = area / max(1.0, region_width * region_height)
        if rectangularity < 0.68 or region_width < width * 0.14 or region_height < height * 0.14:
            continue
        confidence = min(0.97, 0.55 + 0.35 * rectangularity + 0.10 * min(1.0, area_ratio / 0.20))
        candidates.append(Region(x, y, region_width, region_height, 0, confidence))

    return nms(candidates), {
        "background_threshold": round(threshold, 2),
        "foreground_ratio": round(float(np.count_nonzero(mask)) / max(1, image_area), 4),
        "edge_ratio": round(float(np.count_nonzero(edges)) / max(1, image_area), 4),
    }


def edge_support(image: np.ndarray, region: dict[str, Any]) -> float:
    height, width = image.shape[:2]
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    edges = cv2.Canny(gray, 40, 125)
    x1 = max(1, min(width - 2, round(region["x"] / 10000 * width)))
    y1 = max(1, min(height - 2, round(region["y"] / 10000 * height)))
    x2 = max(x1 + 1, min(width - 2, round((region["x"] + region["width"]) / 10000 * width)))
    y2 = max(y1 + 1, min(height - 2, round((region["y"] + region["height"]) / 10000 * height)))
    bands = [edges[y1 - 1 : y1 + 2, x1:x2], edges[y2 - 1 : y2 + 2, x1:x2], edges[y1:y2, x1 - 1 : x1 + 2], edges[y1:y2, x2 - 1 : x2 + 2]]
    samples = sum(band.size for band in bands)
    return sum(int(np.count_nonzero(band)) for band in bands) / max(1, samples)


def layout_key(regions: list[dict[str, Any]]) -> str:
    normalized = [
        [round(region["x"] / 400) * 400, round(region["y"] / 400) * 400, round(region["width"] / 400) * 400, round(region["height"] / 400) * 400]
        for region in regions
    ]
    return json.dumps(normalized, separators=(",", ":"))


def existing_regions(metadata: dict[str, Any]) -> list[dict[str, Any]]:
    proposal = metadata.get("split_proposal")
    if not isinstance(proposal, dict):
        return []
    regions = proposal.get("regions")
    if not isinstance(regions, list):
        return []
    output: list[dict[str, Any]] = []
    for region in regions:
        if not isinstance(region, dict) or region.get("review_state", "included") != "included":
            continue
        output.append(
            {
                "region_id": region.get("region_id"),
                "x": int(region["x"]),
                "y": int(region["y"]),
                "width": int(region["width"]),
                "height": int(region["height"]),
                "rotation_degrees": int(region.get("rotation_degrees", 0)),
                "included": True,
                "confidence": float(region.get("confidence", proposal.get("confidence", 0.0))),
            }
        )
    return output if len(output) >= 2 else []


def analyze_one(metadata: dict[str, Any], thumbnail: Path) -> dict[str, Any]:
    image = cv2.imread(str(thumbnail), cv2.IMREAD_COLOR)
    if image is None:
        raise RuntimeError(f"Unable to decode analysis thumbnail {thumbnail.name}")
    height, width = image.shape[:2]
    candidates, signals = contour_regions(image)
    seams, seam_signals = seam_regions(image)
    signals.update(seam_signals)
    prior_regions = existing_regions(metadata)
    if prior_regions:
        prior_support = float(np.mean([edge_support(image, region) for region in prior_regions]))
        signals["existing_proposal"] = True
        signals["existing_proposal_edge_support"] = round(prior_support, 4)
        signals["existing_proposal_region_count"] = len(prior_regions)
        if len(candidates) >= 2 and prior_support >= 0.16:
            confidence = min(float(region.get("confidence", 0.0)) for region in prior_regions)
            classification = "multi_review" if confidence < 0.92 or prior_support < 0.24 else "multi_high"
            return {
                "engine_version": ENGINE_VERSION,
                "item_id": int(metadata["item_id"]),
                "position": int(metadata["position"]),
                "thumbnail_sha256": image_sha256(thumbnail),
                "classification": classification,
                "confidence": round(min(0.96, 0.62 + 0.20 * confidence + 0.18 * min(1.0, prior_support / 0.30)), 4),
                "method": "independently_validated_familyarchive_split_proposal_v2",
                "regions": prior_regions,
                "signals": signals,
                "review_state": "pending_visual_review",
            }
        signals["existing_proposal_rejected_by_independent_census"] = True

    if len(seams) >= 2:
        seam_confidence = min(region.confidence for region in seams)
        return {
            "engine_version": ENGINE_VERSION,
            "item_id": int(metadata["item_id"]),
            "position": int(metadata["position"]),
            "thumbnail_sha256": image_sha256(thumbnail),
            "classification": "multi_high" if seam_confidence >= 0.82 else "multi_review",
            "confidence": round(seam_confidence, 4),
            "method": "recursive_full_span_seams_v1",
            "regions": [region.basis_points(width, height) for region in seams],
            "signals": signals,
            "review_state": "pending_visual_review",
        }

    area = width * height
    coverage = sum(candidate.width * candidate.height for candidate in candidates) / max(1, area)
    maximum_overlap = max(
        (intersection_over_union(left, right) for index, left in enumerate(candidates) for right in candidates[index + 1 :]),
        default=0.0,
    )
    regions = [candidate.basis_points(width, height) for candidate in candidates]
    minimum_confidence = min((candidate.confidence for candidate in candidates), default=0.0)
    balanced = len(candidates) >= 2 and coverage >= 0.25 and maximum_overlap <= 0.18
    strong = balanced and minimum_confidence >= 0.84 and coverage >= 0.38
    possible = balanced or (len(candidates) >= 2 and minimum_confidence >= 0.70)
    if strong:
        classification = "multi_high"
        confidence = min(0.99, 0.72 + 0.18 * minimum_confidence + 0.10 * min(1.0, coverage))
    elif possible:
        classification = "multi_review"
        confidence = min(0.89, 0.52 + 0.24 * minimum_confidence + 0.08 * min(1.0, coverage))
    elif len(candidates) == 0 or (len(candidates) == 1 and coverage >= 0.58):
        classification = "single_high"
        confidence = min(0.97, 0.91 + 0.04 * (candidates[0].confidence if candidates else 1.0))
        regions = []
    else:
        classification = "ambiguous"
        confidence = round(max(0.25, minimum_confidence * 0.65), 4)

    signals.update({"candidate_count": len(candidates), "coverage": round(coverage, 4), "maximum_overlap": round(maximum_overlap, 4)})
    return {
        "engine_version": ENGINE_VERSION,
        "item_id": int(metadata["item_id"]),
        "position": int(metadata["position"]),
        "thumbnail_sha256": image_sha256(thumbnail),
        "classification": classification,
        "confidence": round(confidence, 4),
        "method": "opencv_background_contours_and_quadrilaterals_v1",
        "regions": regions,
        "signals": signals,
        "review_state": "pending_visual_review" if classification in {"multi_high", "multi_review", "ambiguous"} else "automatic_single",
    }


def apply_templates(records: list[dict[str, Any]], thumbnails: Path) -> int:
    groups: dict[tuple[int, str], list[dict[str, Any]]] = defaultdict(list)
    for record in records:
        if record["classification"] != "multi_high" or len(record["regions"]) < 2:
            continue
        metadata = record.get("metadata", {})
        aspect_bucket = round(float(metadata.get("width", 1)) / max(1.0, float(metadata.get("height", 1))) * 10)
        groups[(aspect_bucket, layout_key(record["regions"]))].append(record)

    promoted = 0
    for record in records:
        if record["classification"] not in {"ambiguous", "multi_review"}:
            continue
        metadata = record.get("metadata", {})
        aspect_bucket = round(float(metadata.get("width", 1)) / max(1.0, float(metadata.get("height", 1))) * 10)
        candidates = [(key, group) for (bucket, key), group in groups.items() if bucket == aspect_bucket and len(group) >= 3]
        if not candidates:
            continue
        image = cv2.imread(str(thumbnails / f"{record['item_id']}.webp"), cv2.IMREAD_COLOR)
        if image is None:
            continue
        best: tuple[float, list[dict[str, Any]], int] | None = None
        for _, group in candidates:
            template = group[0]["regions"]
            score = float(np.mean([edge_support(image, region) for region in template]))
            if best is None or score > best[0]:
                best = (score, template, len(group))
        if best is not None and best[0] >= 0.17:
            record["classification"] = "multi_review"
            record["confidence"] = round(min(0.88, 0.62 + best[0]), 4)
            record["method"] = "verified_recurring_layout_template_v1"
            record["regions"] = [{**region, "confidence": record["confidence"]} for region in best[1]]
            record["signals"]["template_edge_support"] = round(best[0], 4)
            record["signals"]["template_training_count"] = best[2]
            promoted += 1
    return promoted


def write_ledger(path: Path, records: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    with temporary.open("w", encoding="utf-8", newline="\n") as stream:
        for record in sorted(records, key=lambda item: item["position"]):
            public_record = {key: value for key, value in record.items() if key != "metadata"}
            stream.write(json.dumps(public_record, sort_keys=True, separators=(",", ":")) + "\n")
    os.replace(temporary, path)


def render_contact_sheets(
    records: list[dict[str, Any]],
    thumbnails: Path,
    destination: Path,
    page_size: int = 20,
    prefix: str = "review",
) -> int:
    destination.mkdir(parents=True, exist_ok=True)
    for page_index in range(math.ceil(len(records) / page_size)):
        page = records[page_index * page_size : (page_index + 1) * page_size]
        cell_width, cell_height = 300, 250
        columns = 4
        rows = math.ceil(len(page) / columns)
        canvas = np.full((rows * cell_height, columns * cell_width, 3), 245, dtype=np.uint8)
        for index, record in enumerate(page):
            image = cv2.imread(str(thumbnails / f"{record['item_id']}.webp"), cv2.IMREAD_COLOR)
            if image is None:
                continue
            image_height, image_width = image.shape[:2]
            scale = min((cell_width - 12) / image_width, (cell_height - 42) / image_height)
            resized = cv2.resize(image, (max(1, round(image_width * scale)), max(1, round(image_height * scale))), interpolation=cv2.INTER_AREA)
            for region in record["regions"]:
                x1 = round(region["x"] / 10000 * resized.shape[1])
                y1 = round(region["y"] / 10000 * resized.shape[0])
                x2 = round((region["x"] + region["width"]) / 10000 * resized.shape[1])
                y2 = round((region["y"] + region["height"]) / 10000 * resized.shape[0])
                cv2.rectangle(resized, (x1, y1), (x2, y2), (40, 220, 40), 2)
            row, column = divmod(index, columns)
            top = row * cell_height + 30
            left = column * cell_width + 6
            canvas[top : top + resized.shape[0], left : left + resized.shape[1]] = resized
            label = f"{record['item_id']} {record['classification']} {record['confidence']:.2f}"
            cv2.putText(canvas, label, (left, row * cell_height + 21), cv2.FONT_HERSHEY_SIMPLEX, 0.48, (20, 20, 20), 1, cv2.LINE_AA)
        cv2.imwrite(str(destination / f"{prefix}-{page_index + 1:05d}.jpg"), canvas, [cv2.IMWRITE_JPEG_QUALITY, 88])
    return len(records)


def contact_sheets(records: list[dict[str, Any]], thumbnails: Path, destination: Path, page_size: int = 20) -> int:
    queue = [record for record in records if record["review_state"] == "pending_visual_review"]
    return render_contact_sheets(queue, thumbnails, destination, page_size, "review")


def automatic_single_audit_sheets(
    records: list[dict[str, Any]], thumbnails: Path, destination: Path, page_size: int = 20
) -> int:
    singles = [record for record in records if record["classification"] == "single_high"]
    sample = [record for record in singles if int(record["thumbnail_sha256"][:8], 16) % 20 == 0]
    if singles and len(sample) < min(50, len(singles)):
        existing = {int(record["item_id"]) for record in sample}
        for record in sorted(singles, key=lambda value: value["thumbnail_sha256"]):
            if int(record["item_id"]) in existing:
                continue
            sample.append(record)
            existing.add(int(record["item_id"]))
            if len(sample) >= min(50, len(singles)):
                break
    return render_contact_sheets(sample, thumbnails, destination, page_size, "audit")


def run_analyze(arguments: argparse.Namespace) -> int:
    manifest = read_jsonl(arguments.manifest)
    previous = {int(record["item_id"]): record for record in read_jsonl(arguments.ledger)}
    records: list[dict[str, Any]] = []
    failures: list[dict[str, Any]] = []
    pending: dict[Any, tuple[dict[str, Any], Path]] = {}
    completed = 0
    executor = ThreadPoolExecutor(max_workers=max(1, min(arguments.workers, 8)))
    for metadata in manifest:
        item_id = int(metadata["item_id"])
        thumbnail = arguments.thumbnails / f"{item_id}.webp"
        if not thumbnail.exists():
            failures.append({"item_id": item_id, "error": "thumbnail_missing"})
            completed += 1
            continue
        thumb_hash = image_sha256(thumbnail)
        if (
            item_id in previous
            and previous[item_id].get("thumbnail_sha256") == thumb_hash
            and previous[item_id].get("engine_version") == ENGINE_VERSION
        ):
            record = previous[item_id]
            record["metadata"] = {"width": metadata.get("width"), "height": metadata.get("height")}
            records.append(record)
            completed += 1
        else:
            pending[executor.submit(analyze_one, metadata, thumbnail)] = (metadata, thumbnail)

    for future in as_completed(pending):
        metadata, _ = pending[future]
        try:
            record = future.result()
            record["metadata"] = {"width": metadata.get("width"), "height": metadata.get("height")}
            records.append(record)
        except Exception as exception:
            failures.append({"item_id": int(metadata["item_id"]), "error": str(exception)[:300]})
        completed += 1
        if completed % 100 == 0:
            atomic_json(arguments.state, {"status": "running", "processed": completed, "total": len(manifest), "failures": len(failures), "workers": arguments.workers})
    executor.shutdown(wait=True)

    promoted = apply_templates(records, arguments.thumbnails)
    write_ledger(arguments.ledger, records)
    review_count = contact_sheets(records, arguments.thumbnails, arguments.contact_sheets, arguments.page_size)
    audit_count = automatic_single_audit_sheets(
        records,
        arguments.thumbnails,
        arguments.audit_sheets,
        arguments.page_size,
    )
    counts = Counter(record["classification"] for record in records)
    summary = {
        "status": "complete" if len(records) + len(failures) == len(manifest) else "needs_attention",
        "total_manifest": len(manifest),
        "classified": len(records),
        "failures": failures,
        "counts": dict(sorted(counts.items())),
        "template_promotions": promoted,
        "pending_visual_review": review_count,
        "automatic_single_audit_count": audit_count,
        "conceptual_output_count": sum(max(1, len(record["regions"])) for record in records),
    }
    atomic_json(arguments.state, summary)
    print(json.dumps(summary, separators=(",", ":")))
    return 0 if not failures else 2


def self_test() -> int:
    with tempfile.TemporaryDirectory() as temporary:
        root = Path(temporary)
        sheet = np.full((700, 1000, 3), 245, dtype=np.uint8)
        colors = [(40, 80, 190), (175, 95, 40), (60, 155, 70), (140, 55, 155)]
        boxes = [(40, 45, 455, 315), (535, 45, 960, 315), (40, 385, 455, 655), (535, 385, 960, 655)]
        for color, (x1, y1, x2, y2) in zip(colors, boxes, strict=True):
            cv2.rectangle(sheet, (x1, y1), (x2, y2), color, -1)
            cv2.rectangle(sheet, (x1, y1), (x2, y2), (15, 15, 15), 4)
        sheet_path = root / "1.webp"
        cv2.imwrite(str(sheet_path), sheet)
        multi = analyze_one({"item_id": 1, "position": 1}, sheet_path)
        if multi["classification"] not in {"multi_high", "multi_review"} or len(multi["regions"]) != 4:
            raise RuntimeError(f"Synthetic four-photo sheet failed: {multi}")

        adjacent = np.zeros((620, 900, 3), dtype=np.uint8)
        adjacent[:, :450] = (35, 80, 180)
        adjacent[:, 450:] = (185, 120, 45)
        cv2.circle(adjacent[:, :450], (225, 310), 120, (80, 150, 220), -1)
        cv2.rectangle(adjacent, (610, 150), (820, 470), (65, 190, 85), -1)
        adjacent_path = root / "3.webp"
        cv2.imwrite(str(adjacent_path), adjacent)
        adjoining = analyze_one({"item_id": 3, "position": 3}, adjacent_path)
        if adjoining["classification"] not in {"multi_high", "multi_review"} or len(adjoining["regions"]) != 2:
            raise RuntimeError(f"Synthetic adjoining-photo composite failed: {adjoining}")

        single = np.zeros((700, 1000, 3), dtype=np.uint8)
        for y in range(single.shape[0]):
            single[y, :, :] = (80 + y // 8, 100 + y // 10, 120 + y // 12)
        cv2.circle(single, (500, 330), 130, (65, 85, 145), -1)
        single_path = root / "2.webp"
        cv2.imwrite(str(single_path), single)
        scene = analyze_one({"item_id": 2, "position": 2}, single_path)
        if scene["classification"] in {"multi_high", "multi_review"}:
            raise RuntimeError(f"Synthetic ordinary scene was split: {scene}")
    print('{"self_test":"passed"}')
    return 0


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser()
    result.add_argument("--self-test", action="store_true")
    result.add_argument("--manifest", type=Path)
    result.add_argument("--thumbnails", type=Path)
    result.add_argument("--ledger", type=Path)
    result.add_argument("--state", type=Path)
    result.add_argument("--contact-sheets", type=Path)
    result.add_argument("--audit-sheets", type=Path)
    result.add_argument("--page-size", type=int, default=20)
    result.add_argument("--workers", type=int, default=4)
    return result


def main() -> int:
    arguments = parser().parse_args()
    if arguments.self_test:
        return self_test()
    required = [
        arguments.manifest,
        arguments.thumbnails,
        arguments.ledger,
        arguments.state,
        arguments.contact_sheets,
        arguments.audit_sheets,
    ]
    if any(value is None for value in required):
        raise SystemExit("manifest, thumbnails, ledger, state, and contact-sheets are required")
    return run_analyze(arguments)


if __name__ == "__main__":
    sys.exit(main())
