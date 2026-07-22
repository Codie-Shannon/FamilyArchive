<?php
namespace App\Http\Requests\Archive;
use Illuminate\Foundation\Http\FormRequest;
final class EditPhotoMetadataRequest extends FormRequest {
 public function authorize(): bool { return $this->user()?->role === 'owner'; }
 public function rules(): array { $plain=['nullable','string','not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u']; return ['expected_metadata_revision'=>['required','integer','min:0'],'title'=>[...$plain,'max:160'],'description'=>[...$plain,'max:2000'],'story'=>[...$plain,'max:5000'],'change_reason'=>['required','string','min:5','max:500','not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u']]; }
 public function normalized(): array { return ['title'=>$this->norm($this->input('title')),'description'=>$this->norm($this->input('description')),'story'=>$this->norm($this->input('story')),'change_reason'=>trim((string)$this->input('change_reason')),'expected_metadata_revision'=>(int)$this->input('expected_metadata_revision')]; }
 private function norm(mixed $v): ?string { if($v===null)return null; $v=str_replace(["\r\n","\r"],"\n",trim((string)$v)); return $v===''?null:$v; }
}
