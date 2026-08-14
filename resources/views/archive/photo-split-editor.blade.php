<x-layouts::app :title="'Split '.$photo->archive_id">
<main class="mx-auto flex w-full max-w-[1500px] flex-1 flex-col gap-6 p-3 md:p-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ $editorReturnTo }}" class="text-sm font-semibold text-emerald-300">← Return to photo editor</a>
            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-emerald-300">{{ $photo->archive_id }}</p>
            <h1 class="mt-1 text-3xl font-semibold text-white">Split this source into photos</h1>
            <p class="mt-2 max-w-3xl text-zinc-400">The source stays preserved. Each output starts as a complete duplicate so you can define every crop independently before publishing.</p>
        </div>
        <div class="rounded-xl border border-emerald-800 bg-emerald-950/25 px-4 py-3 text-sm text-emerald-100"><strong>Non-destructive:</strong> publishing creates new archive records.</div>
    </header>

    @if($errors->any())<div class="rounded-xl border border-red-700 bg-red-950/30 p-4 text-red-100">{{ $errors->first() }}</div>@endif

    <form id="archive-split-form" method="POST" action="{{ route('archive.photos.editor.split.publish', $photo) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="expected_metadata_revision" value="{{ $photo->metadata_revision }}">
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <input type="hidden" name="editor_return_to" value="{{ $editorReturnTo }}">
        <input id="archive-split-regions" type="hidden" name="regions_json">

        <section class="sticky top-2 z-20 grid gap-3 rounded-2xl border border-zinc-700 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur md:grid-cols-[minmax(0,1fr)_11rem_auto] md:items-end">
            <fieldset>
                <legend class="text-sm font-semibold text-white">Source to split</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    <label class="cursor-pointer rounded-lg border border-zinc-700 px-3 py-2 text-sm text-zinc-200 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-950/40"><input type="radio" name="source_basis" value="current" checked class="mr-2 accent-emerald-500">Current corrected version</label>
                    <label class="cursor-pointer rounded-lg border border-zinc-700 px-3 py-2 text-sm text-zinc-200 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-950/40"><input type="radio" name="source_basis" value="original" class="mr-2 accent-emerald-500">Preserved original</label>
                </div>
            </fieldset>
            <label class="text-sm font-semibold text-white">Number of photos
                <select id="archive-split-count" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                    @for($count = 2; $count <= 20; $count++)<option value="{{ $count }}">{{ $count }}</option>@endfor
                </select>
            </label>
            <button class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950">Publish all split photos</button>
        </section>

        <section class="space-y-3">
            <div><h2 class="text-xl font-semibold text-white">Source</h2><p class="text-sm text-zinc-400">Switching source does not discard your crop boxes. The raw pixel orientation shown here is the exact coordinate basis used when publishing.</p></div>
            <div class="flex min-h-[520px] items-center justify-center overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-950 p-3">
                <img data-split-source-display src="{{ route('archive.photos.editor.source', [$photo, 'split_basis' => 'current']) }}" alt="Selected preserved source" class="max-h-[75vh] max-w-full object-contain" style="image-orientation:none">
            </div>
        </section>

        <section>
            <div><h2 class="text-xl font-semibold text-white">Output photos</h2><p class="mt-1 text-sm text-zinc-400">Every photo begins as the complete source. Drag inside a box to move its crop; drag the lower-right handle to resize it.</p></div>
            <div id="archive-split-outputs" class="mt-4 space-y-8"></div>
        </section>
    </form>
</main>

<template id="archive-split-output-template">
    <article class="space-y-3 rounded-2xl border border-zinc-700 bg-zinc-900 p-3 md:p-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-300" data-output-number></p><h3 class="text-lg font-semibold text-white">Independent photo crop</h3></div>
            <div class="flex gap-2"><button type="button" data-rotate-left class="rounded-lg border border-zinc-600 px-3 py-2 text-sm text-zinc-200">↶ Rotate left</button><button type="button" data-rotate-right class="rounded-lg border border-zinc-600 px-3 py-2 text-sm text-zinc-200">Rotate right ↷</button><button type="button" data-full-source class="rounded-lg border border-zinc-600 px-3 py-2 text-sm text-zinc-200">Use full source</button></div>
        </header>
        <div data-output-stage class="relative mx-auto flex min-h-[520px] w-full touch-none select-none items-center justify-center overflow-hidden rounded-xl bg-zinc-950 p-3">
            <div data-output-image-wrap class="relative w-fit max-w-full">
                <img data-output-image alt="Split output source" class="block max-h-[75vh] max-w-full object-contain" style="image-orientation:none">
                <div data-crop-box class="absolute cursor-move border-2 border-emerald-400 bg-emerald-400/10 shadow-[0_0_0_9999px_rgba(0,0,0,.52)]">
                    <span class="absolute left-1 top-1 rounded bg-zinc-950/90 px-2 py-1 text-xs font-bold text-white" data-crop-label></span>
                    <button type="button" data-resize aria-label="Resize crop" class="absolute bottom-0 right-0 size-8 translate-x-px translate-y-px cursor-se-resize rounded-tl bg-emerald-400 text-zinc-950">↘</button>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-700 bg-zinc-950 p-3">
            <p class="mb-2 text-sm font-semibold text-white">Exact output preview</p>
            <canvas data-output-preview class="mx-auto max-h-[60vh] max-w-full"></canvas>
            <p class="mt-2 text-xs text-zinc-400">This uses the same raw coordinate basis and quarter-turn as the published split. No automatic deskew is added.</p>
        </div>
        <p class="text-sm text-zinc-400" data-output-summary></p>
    </article>
</template>

<script>
(() => {
 const form=document.getElementById('archive-split-form'),container=document.getElementById('archive-split-outputs'),template=document.getElementById('archive-split-output-template'),count=document.getElementById('archive-split-count'),payload=document.getElementById('archive-split-regions');
 const sourceUrls={current:@js(route('archive.photos.editor.source', [$photo, 'split_basis' => 'current'])),original:@js(route('archive.photos.editor.source', [$photo, 'split_basis' => 'original']))};
 let regions=[],activeBitmap=null,bitmapLoad=0; const clamp=(v,min,max)=>Math.max(min,Math.min(max,v));
 const basis=()=>form.elements.source_basis.value;
 const sync=()=>{payload.value=JSON.stringify(regions.map(region=>({x:Math.round(region.x),y:Math.round(region.y),width:Math.round(region.width),height:Math.round(region.height),rotation_degrees:region.rotation_degrees})));};
 const paintPreview=(index,canvas)=>{if(!activeBitmap)return;const r=regions[index],sx=Math.floor(activeBitmap.width*r.x/10000),sy=Math.floor(activeBitmap.height*r.y/10000),sw=Math.max(1,Math.ceil(activeBitmap.width*r.width/10000)),sh=Math.max(1,Math.ceil(activeBitmap.height*r.height/10000)),scale=Math.min(1,1000/Math.max(sw,sh)),crop=document.createElement('canvas');crop.width=Math.max(1,Math.round(sw*scale));crop.height=Math.max(1,Math.round(sh*scale));crop.getContext('2d').drawImage(activeBitmap,sx,sy,sw,sh,0,0,crop.width,crop.height);const turn=((r.rotation_degrees%360)+360)%360;if(turn===90||turn===270){canvas.width=crop.height;canvas.height=crop.width}else{canvas.width=crop.width;canvas.height=crop.height}const context=canvas.getContext('2d');context.save();context.translate(canvas.width/2,canvas.height/2);context.rotate(turn*Math.PI/180);context.drawImage(crop,-crop.width/2,-crop.height/2);context.restore()};
 const paintAllPreviews=()=>container.querySelectorAll('[data-output-preview]').forEach((canvas,index)=>paintPreview(index,canvas));
 const loadBitmap=async()=>{const request=++bitmapLoad;const response=await fetch(sourceUrls[basis()],{headers:{Accept:'image/*'},cache:'no-store'});if(!response.ok)return;const blob=await response.blob(),bitmap=await createImageBitmap(blob,{imageOrientation:'none'});if(request!==bitmapLoad){bitmap.close();return}activeBitmap?.close?.();activeBitmap=bitmap;paintAllPreviews()};
 const begin=(event,index,resize,box,wrap)=>{event.preventDefault();event.stopPropagation();const region=regions[index],bounds=wrap.getBoundingClientRect(),start={x:event.clientX,y:event.clientY,region:{...region}};const move=e=>{const dx=(e.clientX-start.x)/bounds.width*10000,dy=(e.clientY-start.y)/bounds.height*10000;if(resize){region.width=clamp(start.region.width+dx,250,10000-region.x);region.height=clamp(start.region.height+dy,250,10000-region.y)}else{region.x=clamp(start.region.x+dx,0,10000-region.width);region.y=clamp(start.region.y+dy,0,10000-region.height)}paintBox(index,box);sync()};const end=()=>{paintAllPreviews();window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end)};window.addEventListener('pointermove',move);window.addEventListener('pointerup',end)};
 const paintBox=(index,box)=>{const r=regions[index];box.style.cssText=`left:${r.x/100}%;top:${r.y/100}%;width:${r.width/100}%;height:${r.height/100}%`;box.closest('article').querySelector('[data-output-summary]').textContent=`Crop ${Math.round(r.width/100)}% × ${Math.round(r.height/100)}% · ${r.rotation_degrees}° clockwise`;paintPreview(index,box.closest('article').querySelector('[data-output-preview]'))};
 const render=()=>{container.innerHTML='';regions.forEach((region,index)=>{const node=template.content.cloneNode(true),article=node.querySelector('article'),image=node.querySelector('[data-output-image]'),wrap=node.querySelector('[data-output-image-wrap]'),box=node.querySelector('[data-crop-box]');node.querySelector('[data-output-number]').textContent=`Photo ${index+1} of ${regions.length}`;node.querySelector('[data-crop-label]').textContent=String(index+1);image.src=sourceUrls[basis()];box.addEventListener('pointerdown',e=>begin(e,index,false,box,wrap));node.querySelector('[data-resize]').addEventListener('pointerdown',e=>begin(e,index,true,box,wrap));node.querySelector('[data-rotate-left]').addEventListener('click',()=>{region.rotation_degrees=(region.rotation_degrees+270)%360;paintBox(index,box);sync()});node.querySelector('[data-rotate-right]').addEventListener('click',()=>{region.rotation_degrees=(region.rotation_degrees+90)%360;paintBox(index,box);sync()});node.querySelector('[data-full-source]').addEventListener('click',()=>{Object.assign(region,{x:0,y:0,width:10000,height:10000,rotation_degrees:0});paintBox(index,box);sync()});container.appendChild(node);paintBox(index,article.querySelector('[data-crop-box]'))});sync()};
 const setCount=()=>{const wanted=Number(count.value);while(regions.length<wanted)regions.push({x:0,y:0,width:10000,height:10000,rotation_degrees:0});if(regions.length>wanted)regions=regions.slice(0,wanted);render()};
 count.addEventListener('change',setCount);form.querySelectorAll('[name="source_basis"]').forEach(input=>input.addEventListener('change',()=>{document.querySelector('[data-split-source-display]').src=sourceUrls[basis()];activeBitmap=null;render();loadBitmap()}));form.addEventListener('submit',sync);setCount();loadBitmap();
})();
</script>
</x-layouts::app>
