<?php

namespace AlphaDirect\Http\Resources\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PolicyCollection extends ResourceCollection
{
    public $collects = PolicyListResource::class;

    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'meta' => [
                'total'        => $paginated['total'],
                'per_page'     => $paginated['per_page'],
                'current_page' => $paginated['current_page'],
                'last_page'    => $paginated['last_page'],
                'from'         => $paginated['from'],
                'to'           => $paginated['to'],
            ],
        ];
    }
}
