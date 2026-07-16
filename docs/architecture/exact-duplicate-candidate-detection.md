# Exact duplicate candidate detection

Group 06 compares normalized stored SHA-256 facts only. It does not read media bytes or call storage checksum APIs. Eligible retained IncomingUpload records and original MediaFileVersion records can become explicit pending-review DuplicateCandidate targets.

Detection is transactional and idempotent. Candidate rows and the source IncomingUpload state commit together. Exact equality changes the source to `possible_duplicate`; no eligible target changes it to `no_match`. Missing, malformed, uppercase/un-normalized hashes, unretained sources, self matches and derivatives are excluded.

The Owner queue and detail pages are read-only. No duplicate resolution, deletion, replacement, download, promotion or archive approval action exists in Group 06.
