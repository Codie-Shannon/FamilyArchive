# Media Model Decision

Family Archive uses MediaItem as its central archive record.

## Initial Support

- Photo

## Planned Support

- Video
- Document
- Audio

IncomingUpload is separate from MediaItem.

This allows failed, rejected and possible-duplicate uploads to exist without
becoming approved archive records.

MediaFileVersion represents:

- Original
- Edited full
- Web display
- Thumbnail
- Future video or document versions