# Structured Dates and Source Provenance

Group 12 adds reviewable historical dates and physical-source provenance
without changing preserved media.

## Structured Date Model

Date representation and confidence are independent facts:

- `exact` stores a complete accepted calendar date;
- `year_only` stores a reviewed year without inventing a month or day;
- `decade_only` stores a reviewed decade without inventing a year;
- `approximate` stores a calendar date explicitly marked as approximate; and
- `unknown` records that no usable date is currently known.

`StructuredDateConfidence` records confirmed, high, medium, low or unknown
confidence. `DateReviewState` distinguishes accepted dates from suggestions.
Source and reasoning notes explain why the representation was selected.

The original Group 2 `DateConfidence` enum remains unchanged as a compatibility
contract. Group 12 uses a separate structured confidence field so completed
schema and enum behavior is not silently redefined.

## Review Boundary

Only a verified Owner can change structured date facts. Every effective change:

1. locks the approved photo;
2. verifies the expected metadata revision;
3. updates the reviewed database facts;
4. increments the metadata revision exactly once; and
5. appends immutable before/after evidence in the same transaction.

Embedded EXIF dates are not automatically accepted. Group 12 provides no EXIF
acceptance path, automatic rewrite or media mutation.

## Source Records

`SourceCollection` represents a stable physical or conceptual source:

- collection;
- physical album;
- box; or
- envelope.

Every source receives a stable `SRC-` identifier. A source may contain one or
more `ScanBatch` records, each with a stable `SCAN-` identifier, optional scan
date and notes.

`MediaProvenance` links an approved media item to a source and optional scan
batch. A media item can have multiple reviewed provenance links.

## Provenance Revision Evidence

Attaching or removing provenance:

- locks the approved photo;
- rejects stale revision submissions;
- verifies that a scan batch belongs to the selected source;
- prevents duplicate links;
- snapshots stable source and scan-batch identifiers;
- changes only the provenance link; and
- appends one immutable `source_provenance` metadata revision.

Removing a link does not delete the source, scan batch, photo, file version or
media bytes.

## Preservation Boundary

Group 12 does not:

- write, move, rename, replace or delete originals;
- generate or remove derivatives;
- alter archive IDs, storage disks, paths, hashes or byte counts;
- expose original files or storage coordinates;
- accept an inferred or embedded date automatically; or
- introduce events, normalized locations or public provenance browsing.

Events and locations remain Group 13 scope.
