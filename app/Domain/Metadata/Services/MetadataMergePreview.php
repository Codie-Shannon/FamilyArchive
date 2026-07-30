<?php

namespace App\Domain\Metadata\Services;

final class MetadataMergePreview
{
    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $source
     * @return array{decisions: array<string, mixed>, conflicts: array<string, array{target: mixed, source: mixed}>}
     */
    public function preview(array $target, array $source): array
    {
        $decisions = [];
        $conflicts = [];

        foreach (array_unique(array_merge(array_keys($target), array_keys($source))) as $field) {
            $targetValue = $target[$field] ?? null;
            $sourceValue = $source[$field] ?? null;

            if (blank($targetValue) && filled($sourceValue)) {
                $decisions[$field] = $sourceValue;
            } elseif (filled($targetValue) && filled($sourceValue) && $targetValue !== $sourceValue) {
                $conflicts[$field] = [
                    'target' => $targetValue,
                    'source' => $sourceValue,
                ];
            } else {
                $decisions[$field] = $targetValue;
            }
        }

        return [
            'decisions' => $decisions,
            'conflicts' => $conflicts,
        ];
    }
}
