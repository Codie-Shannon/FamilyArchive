<?php
namespace App\Domain\Metadata\Models;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
final class PhotoMetadataRevision extends Model {
 public const UPDATED_AT=null;
 protected $table='media_metadata_revisions';
 protected $guarded=[];
 protected function casts(): array { return ['changed_fields'=>'array','before_values'=>'array','after_values'=>'array','created_at'=>'immutable_datetime']; }
 protected static function booted(): void { static::updating(fn()=>throw new LogicException('Metadata revisions are immutable.')); static::deleting(fn()=>throw new LogicException('Metadata revisions are immutable.')); }
 public function mediaItem(): BelongsTo { return $this->belongsTo(MediaItem::class); }
 public function actor(): BelongsTo { return $this->belongsTo(User::class,'actor_user_id'); }
}
