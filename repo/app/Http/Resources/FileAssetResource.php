<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileAssetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by' => $this->uploaded_by,
            'attachable_type' => $this->attachable_type ? class_basename($this->attachable_type) : null,
            'attachable_id' => $this->attachable_id,
            'created_at' => $this->created_at,
            // storage_path, checksum excluded — internal metadata
        ];
    }
}
