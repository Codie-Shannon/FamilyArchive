<?php

namespace App\Domain\Knowledge\ReadModels;

final readonly class ArchiveKnowledgeSearchResult
{
    public function __construct(
        public string $stable_id,
        public string $label,
        public string $entity_type,
    ) {}
}
