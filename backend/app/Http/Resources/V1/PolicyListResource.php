<?php

namespace AlphaDirect\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for policy list — only the 6 columns shown in the table.
 * No relationship serialization, no conditional whenLoaded checks.
 */
class PolicyListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'policyNumber' => $this->policyNumber,
            'status'       => $this->status,
            'statusLabel'  => match((int) $this->status) {
                1       => 'active',
                2       => 'cancelled',
                3       => 'expired',
                default => 'inactive',
            },
            'premium'      => $this->premium,
            'createdAt'    => optional($this->created_at)->toIso8601String(),
            'customer'     => $this->whenLoaded('customer', fn() => [
                'id'       => $this->customer->id,
                'fullName' => trim(($this->customer->firstName ?? '') . ' ' . ($this->customer->lastName ?? '')),
            ]),
            'product'      => $this->whenLoaded('product', fn() => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
            ]),
        ];
    }
}
