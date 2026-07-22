<?php
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);
function g11Owner():User{return User::factory()->create(['role'=>'owner','email_verified_at'=>now()]);}
function g11Item(User $owner,string $id='G11-001'):MediaItem{return MediaItem::factory()->create(['archive_id'=>$id,'review_status'=>MediaReviewStatus::Approved,'approved_at'=>now(),'approved_by'=>$owner->id,'created_by'=>$owner->id]);}
it('allows only the owner to open the edit form',function(){ $owner=g11Owner();$item=g11Item($owner);$this->get(route('archive.photos.metadata.edit',$item))->assertRedirect('/login');$this->actingAs($owner)->get(route('archive.photos.metadata.edit',$item))->assertOk()->assertSee('Current metadata revision');});
it('updates safe metadata and records one revision',function(){
    $owner=g11Owner();
    $item=g11Item($owner,'G11-UPD');
    $before=[
        'archive_id'=>$item->archive_id,
        'media_type'=>$item->media_type,
        'review_status'=>$item->review_status,
        'visibility'=>$item->visibility,
        'sensitivity_status'=>$item->sensitivity_status,
        'approved_by'=>$item->approved_by,
        'approved_at'=>$item->approved_at?->toISOString(),
    ];
    $this->actingAs($owner)->patch(route('archive.photos.metadata.update',$item),[
        'expected_metadata_revision'=>0,
        'title'=>'Updated fictional title',
        'description'=>"Line one\r\nLine two",
        'story'=>'Updated fictional story',
        'change_reason'=>'Correct fictional descriptive details.',
    ])->assertRedirect(route('archive.photos.show',$item));
    $item->refresh();
    expect($item->metadata_revision)->toBe(1)
        ->and($item->description)->toBe("Line one\nLine two")
        ->and(PhotoMetadataRevision::count())->toBe(1)
        ->and($item->archive_id)->toBe($before['archive_id'])
        ->and($item->media_type)->toBe($before['media_type'])
        ->and($item->review_status)->toBe($before['review_status'])
        ->and($item->visibility)->toBe($before['visibility'])
        ->and($item->sensitivity_status)->toBe($before['sensitivity_status'])
        ->and($item->approved_by)->toBe($before['approved_by'])
        ->and($item->approved_at?->toISOString())->toBe($before['approved_at']);
});
it('rejects stale and no-op submissions',function(){ $owner=g11Owner();$item=g11Item($owner,'G11-STALE');$payload=['expected_metadata_revision'=>0,'title'=>$item->title,'description'=>$item->description,'story'=>$item->story,'change_reason'=>'Confirm unchanged fictional values.'];$this->actingAs($owner)->patch(route('archive.photos.metadata.update',$item),$payload)->assertSessionHasErrors('metadata');$item->update(['metadata_revision'=>1]);$payload['title']='Stale overwrite';$this->actingAs($owner)->patch(route('archive.photos.metadata.update',$item),$payload)->assertSessionHasErrors('expected_metadata_revision');expect(PhotoMetadataRevision::count())->toBe(0);});
