<?php
namespace App\Http\Controllers\Archive;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Actions\UpdatePhotoMetadata;
use App\Domain\Metadata\Exceptions\NoEffectiveMetadataChange;
use App\Domain\Metadata\Exceptions\StaleMetadataRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\EditPhotoMetadataRequest;
final class PhotoMetadataController extends Controller {
 public function edit(MediaItem $mediaItem){$this->eligible($mediaItem);return view('archive.metadata-edit',compact('mediaItem'));}
 public function update(EditPhotoMetadataRequest $request,MediaItem $mediaItem,UpdatePhotoMetadata $action){$this->eligible($mediaItem);try{$updated=$action->handle($mediaItem,$request->user(),$request->normalized());}catch(StaleMetadataRevision $e){return back()->withErrors(['expected_metadata_revision'=>$e->getMessage()])->withInput();}catch(NoEffectiveMetadataChange $e){return back()->withErrors(['metadata'=>$e->getMessage()])->withInput();}return redirect()->route('archive.photos.show',$updated)->with('status',"Metadata revision {$updated->metadata_revision} recorded.");}
 private function eligible(MediaItem $item):void{abort_unless($item->media_type===MediaType::Photo && $item->review_status===MediaReviewStatus::Approved && $item->approved_at!==null,404);}
}
