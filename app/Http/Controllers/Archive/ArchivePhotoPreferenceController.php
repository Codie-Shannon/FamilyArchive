<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Models\UserArchivePreference;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ArchivePhotoPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'integer', Rule::in([2, 4, 8, 16])],
            'previous_rows' => ['nullable', 'integer', Rule::in([2, 4, 8, 16])],
            'current_page' => ['nullable', 'integer', 'min:1'],
            'return_to' => ['nullable', 'string', 'max:2000'],
        ]);
        UserArchivePreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['photo_gallery_rows' => $validated['rows']],
        );

        $returnTo = (string) ($validated['return_to'] ?? '');
        if (! str_starts_with($returnTo, '/archive')) {
            $returnTo = route('archive.index', absolute: false);
        }

        $previousRows = (int) ($validated['previous_rows'] ?? $validated['rows']);
        $currentPage = (int) ($validated['current_page'] ?? 1);
        $firstVisibleOffset = ($currentPage - 1) * $previousRows * 4;
        $anchoredPage = intdiv($firstVisibleOffset, ((int) $validated['rows']) * 4) + 1;
        $parts = parse_url($returnTo);
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['page'] = $anchoredPage;
        $returnTo = (string) ($parts['path'] ?? route('archive.index', absolute: false));
        $returnTo .= '?'.http_build_query($query);

        return redirect($returnTo)->with('status', 'Photo rows preference saved.');
    }
}
