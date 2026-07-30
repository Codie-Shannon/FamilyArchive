<?php

return [
    'version' => env('FAMILY_ARCHIVE_VERSION', '0.14.0'),
    'name' => env('FAMILY_ARCHIVE_RELEASE_NAME', 'People Records and Family Branches'),
    'groups' => env('FAMILY_ARCHIVE_RELEASE_GROUPS', '01-13'),
    'status' => env('FAMILY_ARCHIVE_RELEASE_STATUS', 'Group 14 implemented — evidence pending'),
    'archive_knowledge_prototype_enabled' => env('FAMILY_ARCHIVE_ARCHIVE_KNOWLEDGE_PROTOTYPE', false),
];
