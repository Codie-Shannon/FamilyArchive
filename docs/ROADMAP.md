# Family Archive Roadmap

Family Archive has 46 official build groups. Groups 01-11 are completed and
closed. Group 12 is next.

This file records the repository-safe roadmap. Private chat context and planning
PDFs remain external artifacts and must not be committed.

## Completed Foundation

| Group | Capability |
|---:|---|
| 01 | Application Foundation |
| 02 | Core Archive Schema |
| 03 | Storage Identity and Path Contracts |
| 04 | Controlled Admin Photo Intake |
| 05 | Controlled Quarantine Persistence |
| 06 | Exact Duplicate Candidate Detection |
| 07 | Controlled Manual Duplicate Review |
| 08 | Archive Acceptance and Original Promotion |
| 09 | Private Viewing Derivatives |
| 10 | Private Archive Browsing |
| 11 | Controlled Metadata and Revision History |

## Knowledge Layer

| Group | Capability |
|---:|---|
| 12 | Structured Dates and Source Provenance |
| 13 | Events, Locations and Provenance Browsing |
| 14 | People Records and Family Branches |
| 15 | Family Relationships and Person Tagging |
| 16 | Unknown People and Identity Resolution |
| 17 | Archive Search and Faceted Filtering |
| 18 | Timeline and Entity Exploration |
| 19 | Saved Views and Curated Collections |

## Roles and Access

| Group | Capability |
|---:|---|
| 20 | Role Model and Policy Foundation |
| 21 | Registration, Approval and User Profiles |
| 22 | Visibility, Sensitivity and Branch Access |
| 23 | Original Access Grants and Revocation |

## Contributor Intake

| Group | Capability |
|---:|---|
| 24 | Contributor Photo Intake and Status |
| 25 | Trusted Contributor and Moderation |
| 26 | Mobile and Multi-File Uploads |
| 27 | Upload Templates and Resumable Intake |
| 28 | Stories, Comments and Metadata Suggestions |
| 29 | Identity Suggestions, Corrections and Notifications |

## Processing and Restoration

| Group | Capability |
|---:|---|
| 30 | Processing Jobs and Recipe Versioning |
| 31 | Orientation, Deskew and Auto-Crop Candidates |
| 32 | Exposure, Colour and Tonal Restoration |
| 33 | Noise, Grain, Sharpening and Surface Cleanup |
| 34 | Damage Restoration, Upscaling and Approval Workspace |
| 35 | Batch Profiles, Reprocessing and Quality Regression |

## Cloud Storage and Integrity

| Group | Capability |
|---:|---|
| 36 | Storage Provider Abstraction and Wasabi |
| 37 | Verified Cloud Transfer and Storage Migration |
| 38 | Integrity Manifests and Scheduled Verification |
| 39 | Corruption Detection and Repair Queue |
| 40 | Scan-Batch Import and Inventory |
| 41 | Resumable Processing and 30,000-Photo Scale |
| 42 | Backup Verification and Restore |
| 43 | Disaster Recovery, Monitoring and Capacity |

## Launch and Custodianship

| Group | Capability |
|---:|---|
| 44 | Production Hosting and Security Hardening |
| 45 | Family Pilot, Accessibility and Portfolio Case Study |
| 46 | Family Archive v1.0 Acceptance and Custodianship |

## Group 12 Boundary

Group 12 starts from the verified Group 11 closure. It introduces structured
date modelling and source-provenance foundations.

The group must:

- preserve all completed behavior and tests;
- record where a date or source came from;
- represent confidence, notes and review state explicitly;
- prevent inference from automatically rewriting accepted archive facts;
- use only synthetic New Zealand family-history examples; and
- prove UI, access control, persistence, validation and privacy boundaries.

Detailed implementation decisions remain scoped to Group 12 and must respect
the preservation contracts established by Groups 01-11.
