<?php

namespace App\Support;

final class Release
{
    public static function version(): string
    {
        return (string) config('release.version');
    }

    public static function name(): string
    {
        return (string) config('release.name');
    }

    public static function groups(): string
    {
        return (string) config('release.groups');
    }

    public static function status(): string
    {
        return (string) config('release.status');
    }

    public static function archiveKnowledgePrototypeEnabled(): bool
    {
        return (bool) config('release.archive_knowledge_prototype_enabled', false);
    }
}
