# Screenshot Group 09 Manual Test Plan

Status: closed — screenshot evidence approved.

Use only fictional members, spaces, channels, presence signals, voice messages
and call records. Do not expose real identities, private recordings, storage
keys, checksums, call identifiers, diagnostics, endpoints or credentials.

## Application Evidence

1. Open `/community` as a verified fictional member.
2. Confirm the page identifies v1.4.0 Real-Time Family Community and Post-v1 D.
3. Confirm only spaces covered by active memberships appear.
4. Confirm the selected space shows channels and role counts without raw
   permission overrides.
5. Confirm presence and typing are displayed as expiring states without raw
   timestamps.
6. Confirm only allowed fictional voice messages appear, without storage keys
   or checksums.
7. Open `/admin/community-operations` as the verified fictional Owner.
8. Confirm calls, signalling and TURN all report not configured and the page
   does not claim live-call readiness.
9. Sign out and confirm both routes require authentication; confirm a verified
   non-Owner receives HTTP 403 from the operations route.

## Manual Terminal Evidence

After the three application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-09\Validate-ScreenshotGroup09.ps1
```

Capture the completed PowerShell window as `04_v140_Validation.png`.

The script validates the repository and all three application captures. It
does not capture or approve evidence. The completed validation was captured
manually, and all four evidence files passed visual, privacy, dimension and
PNG metadata inspection before explicit human approval.
