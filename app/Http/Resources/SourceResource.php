<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'source'        => $this->source,
            'source_name'   => $this->source_name,
            'article_count' => $this->article_count,
        ];
    }
}
