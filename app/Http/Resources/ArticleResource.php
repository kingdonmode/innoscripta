<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'source'       => $this->source,
            'source_name'  => $this->source_name,
            'title'        => $this->title,
            'description'  => $this->description,
            'content'      => $this->content,
            'url'          => $this->url,
            'image_url'    => $this->image_url,
            'author'       => $this->author,
            'category'     => $this->category,
            'published_at' => $this->published_at,
        ];
    }
}
