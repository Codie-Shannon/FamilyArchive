<x-layouts::app :title="__('Duplicate Review Queue')">
<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
<header><p class="text-sm text-emerald-300">Owner-only / controlled manual review</p><h1 class="text-3xl font-semibold text-white">Possible exact duplicate review queue</h1><p class="mt-2 text-zinc-400">Human decisions classify relationships only. They never delete, merge, replace, promote or download media.</p></header>
<section class="rounded-xl border border-amber-700 bg-amber-950/30 p-5 text-amber-100"><strong>Possible exact duplicate — manual review required.</strong> <span class="ml-1"><strong>Retained-source boundary:</strong> every candidate source remains in quarantine. Confirming a duplicate does not trigger cleanup or rejection.</span></section>
@php($sections = [['Pending manual review', $pendingCandidates, true], ['Resolved decisions', $resolvedCandidates, false]])
@foreach($sections as [$heading, $candidates, $pending])
<section class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900"><div class="border-b border-zinc-700 px-5 py-4"><h2 class="text-xl font-semibold text-white">{{ $heading }}</h2><p class="text-sm text-zinc-400">{{ $candidates->count() }} candidate(s)</p></div><div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-zinc-800 text-zinc-400"><tr><th class="p-4">Candidate</th><th>Source upload</th><th>Target type</th><th>Target</th><th>SHA-256</th><th>State</th><th>Decision</th></tr></thead><tbody>
@forelse($candidates as $candidate)<tr class="border-t border-zinc-700"><td class="p-4"><a class="text-emerald-300" href="{{ route('admin.duplicate-candidates.show', $candidate) }}">DC-{{ str_pad((string)$candidate->id, 6, '0', STR_PAD_LEFT) }}</a></td><td>{{ $candidate->incomingUpload->upload_id }}</td><td>{{ $candidate->targetType() }}</td><td>{{ $candidate->matchedIncomingUpload?->upload_id ?? ('MediaFileVersion #'.$candidate->matched_media_file_version_id) }}</td><td><code>{{ substr($candidate->matched_sha256, 0, 12) }}…{{ substr($candidate->matched_sha256, -8) }}</code></td><td class="{{ $pending ? 'text-amber-200' : 'text-emerald-200' }}">{{ $candidate->review_state->value }}</td><td>{{ $candidate->resolution?->value ?? '—' }}</td></tr>
@empty<tr><td colspan="7" class="p-8 text-zinc-400">No candidates in this section.</td></tr>@endforelse
</tbody></table></div></section>
@endforeach
<p class="text-sm text-zinc-500">No batch decision, delete, download, replace, merge, promotion or archive-attachment controls exist.</p>
</div></x-layouts::app>
