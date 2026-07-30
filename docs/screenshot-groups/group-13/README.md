# Group 13 Evidence Plan

Status: closed — evidence approved.

This directory contains six application screenshots produced from an isolated,
fictional Group 13 evidence fixture and one PowerShell validation screenshot.
The complete set was human-reviewed and approved as canonical closure evidence.

## Planned Captures

| Filename | Surface | Evidence |
|---|---|---|
| `01_Reviewed_Event_Index.png` | `/archive/events` | Accepted event browsing and uncertain dates |
| `02_Reviewed_Event_Detail.png` | Event detail | Stable identity, date precision and confidence |
| `03_Reviewed_Location_And_Redaction.png` | Location detail | Sensitive precision withheld |
| `04_Event_Source_Provenance.png` | Event detail | Source collection and scan batch link |
| `05_Event_Approved_Media_Link.png` | Approved media reached from the event | Safe archive identity without storage facts |
| `06_Immutable_Event_Location_Revisions.png` | Event/location detail | Actor, reason and revision history |
| `07_Group13_Validation_And_Evidence_Pending.png` | Manual PowerShell validation run | Tests, build, audits, migrations and evidence-pending repository state |

## Capture Verification

- Canvas: 1920 × 1080 PNG
- Application screenshots: 6
- Validation screenshots: 1
- Fixture: isolated SQLite database containing fictional New Zealand records
- Page console errors observed: 0
- Private stored location label rendered: no
- Private stored locality rendered: no
- Original storage path or hash rendered: no
- Human approval: complete

## Capture Rules

- Use synthetic New Zealand history only.
- Do not show real people, addresses, media or archive records.
- Do not show secrets, hashes, storage coordinates or EXIF GPS.
- Verify sensitive location labels are absent from every browse capture.
- The final capture records the evidence-pending state immediately before
  approval and closure.
- All PNGs require human approval before they are committed as canonical
  evidence.
