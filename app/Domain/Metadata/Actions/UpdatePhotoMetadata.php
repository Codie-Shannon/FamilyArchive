<?php
namespace App\Domain\Metadata\Actions;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Exceptions\NoEffectiveMetadataChange;
use App\Domain\Metadata\Exceptions\StaleMetadataRevision;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
final class UpdatePhotoMetadata {
 public function handle(MediaItem $item,User $actor,array $input): MediaItem { return DB::transaction(function()use($item,$actor,$input){ $locked=MediaItem::query()->lockForUpdate()->findOrFail($item->id); abort_unless($locked->media_type===MediaType::Photo && $locked->review_status===MediaReviewStatus::Approved && $locked->approved_at!==null,404); if($locked->metadata_revision!==$input['expected_metadata_revision'])throw new StaleMetadataRevision('The metadata changed after this form was opened.'); $before=[];$after=[]; foreach(['title','description','story'] as $f){ if($locked->getAttribute($f)!==$input[$f]){$before[$f]=$locked->getAttribute($f);$after[$f]=$input[$f];}} if($after===[])throw new NoEffectiveMetadataChange('No metadata values changed.'); $from=$locked->metadata_revision;$to=$from+1; $locked->forceFill([...$after,'metadata_revision'=>$to])->save(); PhotoMetadataRevision::query()->create(['media_item_id'=>$locked->id,'revision_number'=>$to,'actor_user_id'=>$actor->id,'from_revision'=>$from,'to_revision'=>$to,'changed_fields'=>array_keys($after),'before_values'=>$before,'after_values'=>$after,'change_reason'=>$input['change_reason'],'created_at'=>now()]); return $locked->fresh(); }); }
}
