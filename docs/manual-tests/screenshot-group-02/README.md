# Screenshot Group 02 Manual Test Plan

Status: closed — evidence approved.

Use only fictional accounts, conversations and contact messages. Do not enter
real email addresses, family content or identifying network information.

## Application Evidence

1. Open `/dashboard` as the verified fictional Owner and confirm v0.28.0,
   Family Access & Conversation and Build Groups 21–28.
2. Open `/conversations` signed out and confirm only moderated public content
   is visible.
3. Sign in as an approved fictional account, post a fictional message and
   confirm the moderated-display result.
4. Submit the anonymous contact form without a reply email and confirm it
   entered moderation without archive access.
5. Confirm the conversation surface remains usable at mobile width.
6. Confirm a pending account cannot post, a locked thread rejects posts and a
   signed-out user cannot browse `/archive`.

No screenshot may show an email address, IP address, correlation token,
fingerprint, private family content or original storage information.

## Manual Validation

After inspecting the six application screenshots, run
`Validate-ScreenshotGroup02.ps1` yourself from PowerShell. Capture the final
terminal result as `07_v028_Validation.png`.

The script validated the repository but did not capture or approve screenshots.
Screenshot Group 02 closed after all seven files were present, inspected and
approved.
