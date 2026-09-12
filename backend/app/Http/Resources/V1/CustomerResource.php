<?php

namespace AlphaDirect\Http\Resources\V1;

use AlphaDirect\Helpers\PiiMask;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        // DPA/PoPIA: customer PII is masked for non-privileged staff
        // (Super Admin / Manager / Admin see raw). Server-side, fail-closed.
        $fullName = $this->fullName ?? trim($this->firstName . ' ' . $this->lastName);

        return [
            'id'         => $this->id,
            'firstName'  => PiiMask::ifName($this->firstName),
            'middleName' => PiiMask::ifName($this->middleName),
            'lastName'   => PiiMask::ifName($this->lastName),
            'fullName'   => PiiMask::ifName($fullName),
            'email'      => PiiMask::ifEmail($this->email),
            'cellphone'  => PiiMask::ifPhone($this->cellphone),
            'createdAt'  => optional($this->created_at)->toIso8601String(),
            // Never expose: password, api_token, auth_key
        ];
    }
}
