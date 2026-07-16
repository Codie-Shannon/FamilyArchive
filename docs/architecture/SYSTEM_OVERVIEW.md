# Family Archive System Overview

Family Archive is an archive-grade private family media preservation platform.

The platform will preserve original family media, generate optimized viewing
versions, detect possible duplicates, manage human-reviewed metadata and provide
safe family access.

## Core Architecture

The application is a modular Laravel monolith.

Initial supported media:

- Photos

Planned media:

- Videos
- Documents
- Audio
- Other archive records

## Core Records

- IncomingUpload
- MediaItem
- MediaFileVersion
- DuplicateCandidate
- Person
- FamilyBranch
- SourceCollection
- Album
- ScanBatch

## Core Intake Flow

Upload
→ technical validation
→ original preservation
→ checksum
→ duplicate comparison
→ derived file generation
→ manual review
→ approved archive record

## Current Build State

Group 1 establishes the application foundation only.

The archive schema begins in Group 2.