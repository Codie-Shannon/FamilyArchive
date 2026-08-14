@php
    $settings = array_merge([
        'orient' => true, 'quarter_turn' => 0, 'straighten' => 0,
        'crop_left' => 0, 'crop_top' => 0, 'crop_right' => 0, 'crop_bottom' => 0,
        'brightness' => 0, 'contrast' => 0, 'red' => 0, 'green' => 0, 'blue' => 0,
        'denoise' => 0, 'sharpen' => 0, 'cleanup' => 0,
    ], $draft?->settings ?? []);
    $displayPhoto = $current ?? $batchCurrent;
    $batchIndex = $photos->search(fn ($photo) => $photo->id === $batchCurrent->id);
    $filmstripKey = hash('sha256', $photos->pluck('id')->join(','));
    $editorUrl = function ($source, ?int $splitId = null) use ($singlePhotoMode, $returnTo) {
        $parameters = $singlePhotoMode
            ? ['single_photo' => $source->id, 'return_to' => $returnTo]
            : ['photo' => $source->id, 'return_to' => $returnTo];
        if ($splitId !== null) {
            $parameters['split_photo'] = $splitId;
        }

        return route('archive.photos.editor', $parameters);
    };
@endphp
<x-layouts::app :title="'Edit '.$displayPhoto->archive_id">
<main class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-5 p-3 md:p-6" data-archive-photo-editor>
    @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-700 bg-red-950/30 p-4 text-red-100">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ str_starts_with($returnTo, '/archive') ? $returnTo : route('archive.index') }}" class="text-sm font-semibold text-emerald-300">← {{ $singlePhotoMode ? 'Return to photo' : 'Return to selected photos' }}</a>
            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-emerald-300">{{ $batchCurrent->archive_id }}@if(!$singlePhotoMode) · Batch item {{ $batchIndex + 1 }} of {{ $photos->count() }}@endif @if($current && $current->id !== $batchCurrent->id) · Editing {{ $current->archive_id }}@endif</p>
            <h1 class="mt-1 text-3xl font-semibold text-white">Non-destructive photo editor</h1>
            <p class="mt-2 text-zinc-400">Select a source group above the workspace, then choose any saved split beneath its photo.</p>
        </div>
        @if($current)
            <a href="{{ route('archive.photos.editor.split', [$current, 'return_to' => $returnTo, 'editor_return_to' => request()->getRequestUri()]) }}" data-editor-navigation data-prepare-split class="rounded-xl border border-amber-600 px-4 py-3 font-semibold text-amber-100">Split photo</a>
        @endif
    </header>

    <section data-batch-selector>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="font-semibold text-white">{{ $singlePhotoMode ? 'Photo source' : 'Selected batch' }}</h2>
            <span class="text-xs text-zinc-400">Position and selection are preserved</span>
        </div>
        <nav data-editor-filmstrip data-filmstrip-key="batch:{{ $filmstripKey }}" class="flex gap-3 overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 p-3" aria-label="Selected source photos">
            @foreach($photos as $photo)
                <a href="{{ $editorUrl($photo) }}" data-editor-navigation data-active-photo="{{ $photo->id === $batchCurrent->id ? 'true' : 'false' }}" data-batch-photo-id="{{ $photo->id }}" class="w-36 shrink-0 overflow-hidden rounded-xl border {{ $photo->id === $batchCurrent->id ? 'border-emerald-400 bg-emerald-950/30' : 'border-zinc-700 bg-zinc-950' }}" title="Open {{ filled($photo->title) ? $photo->title : $photo->archive_id }}">
                    <div class="flex aspect-square items-center justify-center bg-zinc-950">
                        <img src="{{ route('archive.photos.editor.thumbnail', [$photo, 'basis' => 'original']) }}" alt="{{ filled($photo->title) ? $photo->title : $photo->archive_id }}" class="size-full object-cover">
                    </div>
                    <div class="truncate p-2 text-xs text-zinc-300">{{ $photo->archive_id }}</div>
                </a>
            @endforeach
        </nav>
    </section>

    @if($previewOnly)
        <div class="rounded-xl border border-amber-600 bg-amber-950/25 p-4 text-amber-100" role="status">
            <strong>Original preview only.</strong> This preserved source is not a saved item on the Photos page. Select one of its saved photos beneath the preview to edit it.
        </div>
        <section class="grid min-h-0 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-4">
                <div class="flex min-h-[520px] items-center justify-center overflow-hidden rounded-2xl border border-amber-800 bg-zinc-950 p-3 xl:min-h-[calc(100vh-20rem)]">
                    <img src="{{ route('archive.photos.editor.source', [$batchCurrent, 'split_basis' => 'original']) }}" alt="Preserved original preview" class="max-h-[78vh] max-w-full object-contain">
                </div>
                @include('archive.partials.photo-editor-split-selector')
            </div>
            <aside class="self-start rounded-2xl border border-zinc-700 bg-zinc-900 p-5 xl:sticky xl:top-3">
                <h2 class="font-semibold text-white">Choose a saved photo to edit</h2>
                <p class="mt-2 text-sm text-zinc-400">The original is deliberately preview-only. Crop, rotate, adjustment, and save tools become available after you select a saved split below it.</p>
            </aside>
        </section>
    @else
        <form id="archive-editor-form" method="POST" action="{{ route('archive.photos.editor.draft', $current) }}" class="grid min-h-0 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            @csrf @method('PUT')
            <div class="space-y-4">
                <section class="flex min-h-[520px] items-center justify-center overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-950 p-3 xl:min-h-[calc(100vh-18rem)]"><canvas id="archive-editor-canvas" class="max-h-[78vh] max-w-full"></canvas></section>
                @include('archive.partials.photo-editor-split-selector')
            </div>
            <aside class="self-start space-y-4 xl:sticky xl:top-3">
                <nav class="hidden grid-cols-3 gap-2 rounded-xl border border-zinc-700 bg-zinc-950 p-2 xl:grid" aria-label="Editing tools">@foreach(['crop'=>'Crop & rotate','adjust'=>'Adjust','detail'=>'Detail'] as $tool=>$label)<button type="button" data-editor-tool="{{ $tool }}" class="rounded-lg px-2 py-2 text-sm font-semibold text-zinc-300">{{ $label }}</button>@endforeach</nav>
                @if($isSplit)<fieldset data-tool-panel="crop" class="rounded-2xl border border-amber-700 bg-amber-950/20 p-4"><legend class="px-1 font-semibold text-amber-100">Split photo source</legend><label class="mt-2 flex gap-3 text-sm text-zinc-200"><input type="checkbox" name="from_source_scan" value="1" @checked($draft?->from_source_scan) class="mt-1">Start again from the full preserved source scan</label><p class="mt-2 text-xs text-zinc-400">Off edits this split only. On lets you redefine a poor initial crop; sibling splits remain unchanged.</p></fieldset>@endif
                <fieldset data-tool-panel="crop" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4"><legend class="px-1 font-semibold text-white">Crop & rotate</legend><input type="hidden" name="orient" value="1"><input id="archive-quarter-turn" name="quarter_turn" type="hidden" value="{{ $settings['quarter_turn'] }}"><div class="grid grid-cols-2 gap-2"><button type="button" data-rotate="-1" class="rounded-lg border border-zinc-600 p-2 text-zinc-200">↶ Rotate left</button><button type="button" data-rotate="1" class="rounded-lg border border-zinc-600 p-2 text-zinc-200">Rotate right ↷</button></div><label class="mt-4 block text-sm text-zinc-200">Straighten <output data-readout="straighten"></output><input data-editor-control name="straighten" type="range" min="-8" max="8" step="0.1" value="{{ $settings['straighten'] }}" class="mt-2 w-full accent-emerald-500"></label>@foreach(['crop_left'=>'Left','crop_top'=>'Top','crop_right'=>'Right','crop_bottom'=>'Bottom'] as $name=>$label)<label class="mt-3 block text-sm text-zinc-200">{{ $label }} <output data-readout="{{ $name }}"></output><input data-editor-control name="{{ $name }}" type="range" min="0" max="80" step="0.5" value="{{ $settings[$name] }}" class="mt-1 w-full accent-emerald-500"></label>@endforeach</fieldset>
                <fieldset data-tool-panel="adjust" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4"><legend class="px-1 font-semibold text-white">Exposure & colour</legend>@foreach(['brightness'=>['Brightness',-40,40],'contrast'=>['Contrast',-30,30],'red'=>['Red',-20,20],'green'=>['Green',-20,20],'blue'=>['Blue',-20,20]] as $name=>[$label,$min,$max])<label class="mb-3 block text-sm text-zinc-200">{{ $label }} <output data-readout="{{ $name }}"></output><input data-editor-control name="{{ $name }}" type="range" min="{{ $min }}" max="{{ $max }}" step="1" value="{{ $settings[$name] }}" class="mt-1 w-full accent-emerald-500"></label>@endforeach</fieldset>
                <fieldset data-tool-panel="detail" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4"><legend class="px-1 font-semibold text-white">Detail</legend>@foreach(['denoise'=>['Denoise',3],'sharpen'=>['Sharpen',2],'cleanup'=>['Cleanup',3]] as $name=>[$label,$max])<label class="mb-3 block text-sm text-zinc-200">{{ $label }} <output data-readout="{{ $name }}"></output><input data-editor-control name="{{ $name }}" type="range" min="0" max="{{ $max }}" step="1" value="{{ $settings[$name] }}" class="mt-1 w-full accent-emerald-500"></label>@endforeach</fieldset>
                <div class="space-y-3 rounded-2xl border border-emerald-700 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur">
                    <div class="grid grid-cols-2 gap-2"><button type="button" data-save-draft class="rounded-xl border border-emerald-600 px-4 py-3 font-semibold text-emerald-200">Save draft</button><button type="button" data-reset-editor class="rounded-xl border border-zinc-600 px-4 py-3 text-zinc-200">Reset</button></div>
                    <p data-autosave-state class="text-xs text-zinc-400">Draft changes autosave after you pause.</p>
                </div>
            </aside>
        </form>
    @endif

    <section class="flex flex-col gap-3 rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div><strong class="text-white"><span data-draft-count>{{ $draftCount }}</span> changed <span data-draft-label>{{ Str::plural('photo', $draftCount) }}</span></strong><p class="text-sm text-zinc-400">Each saved photo keeps its own draft and immutable original.</p></div>
        <div class="flex gap-2">
            @if($current)<form method="POST" action="{{ route('archive.photos.editor.publish', $current) }}" data-publish-current-form>@csrf<button data-publish-current class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40" @disabled(!$draft || ($batchEdit?->isActive() ?? false))>Save this photo</button></form>@endif
            @if(!$singlePhotoMode)<form method="POST" action="{{ route('archive.photos.editor.publish-all') }}" data-publish-all-form>@csrf<button data-publish-all class="rounded-xl border border-emerald-600 px-5 py-3 font-semibold text-emerald-200 disabled:cursor-not-allowed disabled:opacity-40" @disabled($draftCount === 0 || ($batchEdit?->isActive() ?? false))>Save all changed</button></form>@endif
        </div>
    </section>

    @if($batchEdit)
        @php($batchProcessed = $batchEdit->completed_count + $batchEdit->failed_count)
        @php($batchPercent = $batchEdit->total_count > 0 ? min(100, (int) round(($batchProcessed / $batchEdit->total_count) * 100)) : 100)
        <section data-photo-save-progress data-status-url="{{ route('archive.photos.editor.publish-all.status', $batchEdit) }}" class="rounded-2xl border border-sky-700 bg-sky-950/20 p-4" aria-live="polite">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><strong class="text-white">Saving changed photos in the background</strong><p data-save-progress-label class="mt-1 text-sm text-sky-100">{{ $batchProcessed }} of {{ $batchEdit->total_count }} processed@if($batchEdit->failed_count > 0); {{ $batchEdit->failed_count }} need retry@endif.</p></div>
                <span data-save-progress-percent class="text-xl font-semibold text-white">{{ $batchPercent }}%</span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-800"><div data-save-progress-bar class="h-full bg-sky-400 transition-[width]" style="width: {{ $batchPercent }}%"></div></div>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('archive.photos.editor.publish-all.retry', $batchEdit) }}" data-save-retry @if(!($batchEdit->state === 'completed_with_failures' && $batchEdit->failed_count > 0)) hidden @endif>@csrf<button class="rounded-lg border border-amber-600 px-3 py-2 text-sm font-semibold text-amber-100">Retry failed photos</button></form>
                <button type="button" data-refresh-editor class="rounded-lg border border-zinc-600 px-3 py-2 text-sm text-zinc-200">Refresh editor</button>
            </div>
        </section>
    @endif
</main>

<script>
(() => {
 const strips=Array.from(document.querySelectorAll('[data-editor-filmstrip]'));
 const stripKey=strip=>`familyarchive:editor-filmstrip:${strip.dataset.filmstripKey}`;
 const storeStrips=()=>strips.forEach(strip=>sessionStorage.setItem(stripKey(strip),String(strip.scrollLeft)));
 strips.forEach(strip=>{const stored=sessionStorage.getItem(stripKey(strip));if(stored!==null){requestAnimationFrame(()=>{strip.scrollLeft=Number(stored)})}else{const active=strip.querySelector('[data-active-photo="true"]');if(active)requestAnimationFrame(()=>{strip.scrollLeft=Math.max(0,active.offsetLeft-strip.clientWidth/2+active.clientWidth/2)})}strip.addEventListener('scroll',()=>sessionStorage.setItem(stripKey(strip),String(strip.scrollLeft)),{passive:true})});
 document.querySelectorAll('[data-editor-navigation]').forEach(link=>link.addEventListener('click',storeStrips));
})();
</script>

@if($batchEdit)
<script>
(() => {
 const panel=document.querySelector('[data-photo-save-progress]'),label=panel.querySelector('[data-save-progress-label]'),percent=panel.querySelector('[data-save-progress-percent]'),bar=panel.querySelector('[data-save-progress-bar]'),retry=panel.querySelector('[data-save-retry]'),refresh=panel.querySelector('[data-refresh-editor]');let polling;
 const render=data=>{percent.textContent=`${data.percent}%`;bar.style.width=`${data.percent}%`;label.textContent=`${data.processed} of ${data.total} processed${data.failed?`; ${data.failed} need retry`:'.'}`;retry.hidden=!data.retryable;if(!data.active&&polling){clearInterval(polling);polling=null}};
 const check=async()=>{try{const response=await fetch(panel.dataset.statusUrl,{headers:{Accept:'application/json'}});if(response.ok)render(await response.json())}catch{label.textContent='Progress will resume when the connection returns.'}};
 refresh.addEventListener('click',()=>location.reload());check();@if($batchEdit->isActive()) polling=setInterval(check,1500); @endif
})();
</script>
@endif

@if($current)
<img id="archive-editor-source" src="{{ route('archive.photos.editor.source', [$current, 'source_scan' => $draft?->from_source_scan ? 1 : 0]) }}" alt="" class="hidden">
<script>
(() => {
 const form=document.getElementById('archive-editor-form'),source=document.getElementById('archive-editor-source'),canvas=document.getElementById('archive-editor-canvas'),ctx=canvas.getContext('2d'),turn=document.getElementById('archive-quarter-turn'),controls=Array.from(form.querySelectorAll('[data-editor-control]')),state=document.querySelector('[data-autosave-state]'),publishCurrent=document.querySelector('[data-publish-current]'),publishAll=document.querySelector('[data-publish-all]'),publishCurrentForm=document.querySelector('[data-publish-current-form]'),draftCountNode=document.querySelector('[data-draft-count]'),draftLabel=document.querySelector('[data-draft-label]'),batchActive={{ ($batchEdit?->isActive() ?? false) ? 'true' : 'false' }};let timer,saving=null,changeRevision=0,savedRevision=0,hasDraft={{ $draft ? 'true' : 'false' }},activeTool='crop';
 const n=name=>Number(form.elements[name]?.value||0);const rotate=(input,degrees)=>{if(!degrees)return input;const r=degrees*Math.PI/180,s=Math.abs(Math.sin(r)),c=Math.abs(Math.cos(r)),out=document.createElement('canvas');out.width=Math.max(1,Math.ceil(input.width*c+input.height*s));out.height=Math.max(1,Math.ceil(input.width*s+input.height*c));const x=out.getContext('2d');x.fillStyle='#111';x.fillRect(0,0,out.width,out.height);x.translate(out.width/2,out.height/2);x.rotate(r);x.drawImage(input,-input.width/2,-input.height/2);return out};
 const render=()=>{if(!source.complete||!source.naturalWidth)return;const scale=Math.min(1,1400/Math.max(source.naturalWidth,source.naturalHeight));let work=document.createElement('canvas');work.width=Math.max(1,Math.round(source.naturalWidth*scale));work.height=Math.max(1,Math.round(source.naturalHeight*scale));work.getContext('2d').drawImage(source,0,0,work.width,work.height);work=rotate(work,n('quarter_turn')*-90-n('straighten'));const l=n('crop_left')/100,t=n('crop_top')/100,r=n('crop_right')/100,b=n('crop_bottom')/100,sx=Math.round(work.width*l),sy=Math.round(work.height*t),sw=Math.max(1,Math.round(work.width*(1-l-r))),sh=Math.max(1,Math.round(work.height*(1-t-b)));canvas.width=Math.min(1400,sw);canvas.height=Math.max(1,Math.round(sh*canvas.width/sw));ctx.filter=`brightness(${100+n('brightness')}%) contrast(${100+n('contrast')}%) blur(${(n('denoise')+n('cleanup'))*.18}px)`;ctx.drawImage(work,sx,sy,sw,sh,0,0,canvas.width,canvas.height);ctx.filter='none';controls.forEach(c=>{const o=form.querySelector(`[data-readout="${c.name}"]`);if(o)o.textContent=c.name.startsWith('crop_')?`${c.value}%`:c.name==='straighten'?`${c.value}°`:c.value})};
 const errorMessage=async response=>{try{const payload=await response.json();const errors=payload.errors?Object.values(payload.errors).flat():[];return errors[0]||payload.message||'The draft could not be saved.'}catch{return 'The draft could not be saved.'}};
 const hasUnsaved=()=>savedRevision<changeRevision;
 const markChanged=()=>{changeRevision++;state.textContent='Draft has unsaved changes…';clearTimeout(timer)};
 const saveNow=()=>{if(!hasUnsaved())return Promise.resolve(true);if(saving)return saving;clearTimeout(timer);saving=(async()=>{try{while(hasUnsaved()){const snapshotRevision=changeRevision,body=new FormData(form);body.set('_method','PUT');state.textContent='Saving draft…';const res=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body});if(!res.ok){state.textContent=await errorMessage(res);return false}savedRevision=Math.max(savedRevision,snapshotRevision);if(!batchActive&&publishCurrent)publishCurrent.disabled=false;if(!batchActive&&publishAll)publishAll.disabled=false;if(!hasDraft){hasDraft=true;const count=Number(draftCountNode.textContent||0)+1;draftCountNode.textContent=String(count);draftLabel.textContent=count===1?'photo':'photos'}}state.textContent='Draft autosaved.';return true}catch{state.textContent='The server could not be reached. Your changes remain in this editor.';return false}finally{saving=null}})();return saving};
 const autosave=()=>{markChanged();timer=setTimeout(saveNow,900)};
 const publishDraft=async()=>{if(!hasDraft||!publishCurrentForm)return true;state.textContent='Saving this photo before opening the split editor…';try{const res=await fetch(publishCurrentForm.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:new FormData(publishCurrentForm)});if(!res.ok){state.textContent=await errorMessage(res);return false}hasDraft=false;state.textContent='Photo saved. Opening split editor…';return true}catch{state.textContent='The photo could not be saved before splitting. Your draft remains available.';return false}};
 const tools=()=>{document.querySelectorAll('[data-editor-tool]').forEach(button=>button.className=`rounded-lg px-2 py-2 text-sm font-semibold ${button.dataset.editorTool===activeTool?'bg-emerald-500 text-zinc-950':'text-zinc-300'}`);if(matchMedia('(min-width:1280px)').matches)document.querySelectorAll('[data-tool-panel]').forEach(panel=>panel.hidden=panel.dataset.toolPanel!==activeTool);else document.querySelectorAll('[data-tool-panel]').forEach(panel=>panel.hidden=false)};
 document.querySelectorAll('[data-editor-tool]').forEach(button=>button.addEventListener('click',()=>{activeTool=button.dataset.editorTool;tools()}));addEventListener('resize',tools);tools();
 document.querySelectorAll('[data-editor-navigation]').forEach(link=>link.addEventListener('click',async event=>{const preparesSplit=link.hasAttribute('data-prepare-split');if(!hasUnsaved()&&!(preparesSplit&&hasDraft))return;event.preventDefault();if(!await saveNow())return;if(preparesSplit&&!await publishDraft())return;location.assign(link.href)}));
 document.querySelectorAll('[data-publish-current-form],[data-publish-all-form]').forEach(publishForm=>publishForm.addEventListener('submit',async event=>{if(!hasUnsaved())return;event.preventDefault();if(await saveNow())publishForm.submit()}));
 controls.forEach(c=>c.addEventListener('input',()=>{render();autosave()}));document.querySelectorAll('[data-rotate]').forEach(b=>b.addEventListener('click',()=>{turn.value=Math.max(-2,Math.min(2,n('quarter_turn')+Number(b.dataset.rotate)));render();autosave()}));form.elements.from_source_scan?.addEventListener('change',async()=>{markChanged();if(await saveNow())location.reload()});document.querySelector('[data-save-draft]').addEventListener('click',()=>{markChanged();saveNow()});document.querySelector('[data-reset-editor]').addEventListener('click',()=>{controls.forEach(c=>c.value=0);turn.value=0;render();autosave()});source.addEventListener('load',render);if(source.complete)render();window.addEventListener('beforeunload',event=>{if(hasUnsaved()||saving){event.preventDefault();event.returnValue=''}});
})();
</script>
@endif
</x-layouts::app>
