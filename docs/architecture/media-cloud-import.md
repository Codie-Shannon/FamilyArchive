# Media and Cloud Import

Screenshot Group 07 introduces the v1.2.0 import-planning boundary for selected
photo, video, audio and document records.

## Provider Boundary

- Google Photos uses a user-selected Picker workflow and reports not configured
  until both required application credentials exist.
- Apple Photos defaults to a manual export pathway.
- Native Apple Photos access remains unvalidated until it is exercised on
  suitable Apple hardware.
- Provider credentials are configuration secrets and never appear in source,
  rendered evidence or import manifests.

## Preservation Boundary

Planning creates a preflight session and selected-item records only. It does not
accept media directly into the archive. Every selected item must still pass the
existing validation, quarantine retention, duplicate review and human
acceptance workflow.

Displayed source names are reduced to their basename. Import records may carry
provider identifiers and safe source metadata, but never provider credentials.

## Media Scope

The planning schema recognizes photo, video, audio and document selections.
Versioned playback-profile records reserve controlled recipes for non-photo
media. This release does not claim complete derivative, playback, accessibility
or recovery support for those media types.

Document OCR and searchable scan text remain explicitly excluded.
