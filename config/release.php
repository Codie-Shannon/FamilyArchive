<?php

return [
    'version' => env('FAMILY_ARCHIVE_VERSION', '0.12.0'),
    'name' => env('FAMILY_ARCHIVE_RELEASE_NAME', 'Structured Dates and Source Provenance'),
    'groups' => env('FAMILY_ARCHIVE_RELEASE_GROUPS', '01-12'),
    'status' => env('FAMILY_ARCHIVE_RELEASE_STATUS', 'Group 13 implementation complete — evidence pending'),
    'archive_knowledge_prototype_enabled' => env('FAMILY_ARCHIVE_ARCHIVE_KNOWLEDGE_PROTOTYPE', false),
];
