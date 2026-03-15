<?php

namespace App\Contracts;

use App\DTOs\ArticleDto;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface NewsFetcherInterface
{
    /**
     * Fetch recent articles from the source.
     *
     * @param  array $options  e.g. ['query' => 'tech', 'from' => Carbon, 'page' => 1, 'pageSize' => 50]
     * @return Collection<int, ArticleDto>
     */
    public function fetch(array $options = []): Collection;

    /**
     * Normalize a single raw API response item into a typed DTO.
     */
    public function normalize(array $rawArticle): ArticleDto;

    public function getSourceName(): string;

    /** Slug identifier, e.g. 'newsapi', 'guardian', 'nytimes' */
    public function getSourceId(): string;
}
