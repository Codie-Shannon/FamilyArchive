<?php

return [
    'version' => env('FAMILY_ARCHIVE_VERSION', '0.13.0'),
    'name' => env('FAMILY_ARCHIVE_RELEASE_NAME', 'Events, Locations and Provenance Browsing'),
    'groups' => env('FAMILY_ARCHIVE_RELEASE_GROUPS', '01-13'),
    'status' => env('FAMILY_ARCHIVE_RELEASE_STATUS', 'Group 13 closed — Group 14 next'),
    'archive_knowledge_prototype_enabled' => env('FAMILY_ARCHIVE_ARCHIVE_KNOWLEDGE_PROTOTYPE', false),
];
