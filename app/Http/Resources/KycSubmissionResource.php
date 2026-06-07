<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'document_id'    => $this->kyc_document_id,
            'document_name'  => $this->document?->name,
            'user_id'        => $this->user_id,
            'user_name'      => $this->user?->name,
            'user_email'     => $this->user?->email,
            'original_name'  => $this->original_name,
            'mime_type'      => $this->mime_type,
            'size'           => $this->size,
            'status'         => $this->status,
            'review_note'    => $this->review_note,
            'reviewed_by'    => $this->reviewer?->name,
            'reviewed_at'    => $this->reviewed_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
