<?php

namespace AlphaDirect\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug ?? null,
            'status'     => $this->status,
            'type'       => is_string($this->type) ? $this->type : ($this->type->name ?? null),
            'hasVehicle' => (bool) ($this->has_vehicle ?? false),
            'hasMember'  => (bool) ($this->has_member ?? false),
            // Whether the product appears on the customer-facing start site.
            // Toggled via PATCH /api/v1/products/{id}/visibility.
            'isForStart' => (bool) ($this->isForStart ?? false),
        ];
    }
}
