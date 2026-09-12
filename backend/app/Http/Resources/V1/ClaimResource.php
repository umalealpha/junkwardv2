<?php

namespace AlphaDirect\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ClaimResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'claimNumber' => $this->claim_number,
            'claimType'   => $this->claim_type,
            'status'      => $this->status,
            'createdAt'   => optional($this->created_at)->toIso8601String(),
        ];
    }
}
