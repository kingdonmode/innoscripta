<?php

namespace App\Contracts;

use Illuminate\Support\Collection;
use Carbon\Carbon;

interface NewsFetcherInterface
{
  /**
   * Fetch recent articles (e.g., last 24h or since last fetch).
   *
   * @param array $options  e.g. ['query' => 'tech', 'from' => Carbon, 'page' => 1, 'pageSize' => 50]
   * @return Collection     standardized article items
   */
  public function fetch(array $options = []): Collection;

  /**
   * Normalize raw API response to consistent format.
   * Every fetcher must return articles in this shape.
   */
  public function normalize(array $rawArticle): array;

  public function getSourceName(): string;
  public function getSourceId(): string; // slug, e.g. 'newsapi', 'guardian', 'nytimes'
}
