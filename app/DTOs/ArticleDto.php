<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class ArticleDto
{
    public function __construct(
        public string  $externalId,
        public string  $source,
        public string  $title,
        public ?string $description,
        public ?string $content,
        public string  $url,
        public ?string $imageUrl,
        public ?string $author,
        public ?string $category,
        public string  $sourceName,
        public Carbon  $publishedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'external_id'  => $this->externalId,
            'source'       => $this->source,
            'title'        => $this->title,
            'description'  => $this->description,
            'content'      => $this->content,
            'url'          => $this->url,
            'image_url'    => $this->imageUrl,
            'author'       => $this->author,
            'category'     => $this->category,
            'source_name'  => $this->sourceName,
            'published_at' => $this->publishedAt,
        ];
    }
}
