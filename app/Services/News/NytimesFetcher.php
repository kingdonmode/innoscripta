<?php

namespace App\Services\News;

use App\Contracts\NewsFetcherInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NytimesFetcher implements NewsFetcherInterface
{
  protected string $apiKey;
  protected string $baseUrl = 'https://api.nytimes.com/svc/news/v3';

  public function __construct()
  {
    $this->apiKey = config('services.nytimes.key');

    if (empty($this->apiKey)) {
      throw new \RuntimeException('NYTimes API key is missing in config/services.php');
    }
  }

  /**
   * Fetch recent articles from NYT TimesWire.
   *
   * Options:
   * - section: string (e.g. 'all', 'world', 'technology', 'business', 'arts') – default 'all'
   * - source:  string (nyt, inyt, all) – default 'all'
   * - limit:   int (20–500, multiples of 20) – default 40
   * - from:    Carbon|string (not directly supported; we can simulate by fetching more and filtering client-side if needed)
   *
   * Note: This endpoint is for latest articles; no keyword search or exact date range.
   *       For keyword/date search → consider using NYT Article Search API instead.
   */
  public function fetch(array $options = []): Collection
  {
    $defaultOptions = [
      'section' => $options['section'] ?? 'all',      // or 'technology', 'world', etc.
      'source'  => $options['source']  ?? 'all',      // nyt | inyt | all
      'limit'   => $options['limit']   ?? 40,
    ];

    $options = array_merge($defaultOptions, $options);

    // Enforce limit rules (multiples of 20, 20–500)
    $limit = (int) $options['limit'];
    $limit = max(20, min(500, $limit));
    $limit = round($limit / 20) * 20; // nearest multiple of 20

    // Build path: /content/{source}/{section}.json
    $path = "content/{$options['source']}/{$options['section']}.json";

    $queryParams = [
      'limit'   => $limit,
      'api-key' => $this->apiKey,
    ];

    try {
      $response = Http::get("{$this->baseUrl}/{$path}", $queryParams);

      if (!$response->successful()) {
        Log::error('NYTimes TimesWire fetch failed', [
          'status' => $response->status(),
          'body'   => $response->body(),
          'params' => $queryParams,
          'path'   => $path,
        ]);
        return collect();
      }

      $data = $response->json();

      if (($data['status'] ?? 'ERROR') !== 'OK') {
        Log::warning('NYTimes TimesWire returned non-OK status', ['response' => $data]);
        return collect();
      }

      $articles = $data['results'] ?? []; // key is 'results' in TimesWire

      return collect($articles)->map(fn(array $item) => $this->normalize($item));
    } catch (\Exception $e) {
      Log::error('NYTimes TimesWire request exception', ['error' => $e->getMessage()]);
      return collect();
    }
  }

  public function normalize(array $item): array
  {
    $publishedAt = $item['published_date'] ?? now()->toIso8601String();

    // Multimedia: find the best image (often 'mediumThreeByTwo440' or 'superJumbo')
    $imageUrl = null;
    if (!empty($item['multimedia'])) {
      // Pick largest available or a standard one
      $image = collect($item['multimedia'])->firstWhere('format', 'superJumbo')
        ?? collect($item['multimedia'])->firstWhere('format', 'mediumThreeByTwo440')
        ?? collect($item['multimedia'])->first();
      $imageUrl = $image['url'] ?? null;
    }

    $abstract    = $item['abstract']    ?? '';
    $subheadline = $item['subheadline'] ?? '';

    // Build content from every available text field – TimesWire has no full article body.
    $contentParts = array_filter([
      $abstract,
      $subheadline,
      $this->joinFacets('Topics',        $item['des_facet'] ?? []),
      $this->joinFacets('Organizations', $item['org_facet'] ?? []),
      $this->joinFacets('People',        $item['per_facet'] ?? []),
      $this->joinFacets('Locations',     $item['geo_facet'] ?? []),
    ]);
    $content = implode("\n\n", $contentParts);

    // Prefer subsection for a more specific category label.
    $category = $item['subsection'] ?: ($item['section'] ?? null);

    return [
      'external_id'   => md5($item['url'] ?? uniqid('nytimes_', true)),
      'source'        => $this->getSourceId(),
      'title'         => $item['title']  ?? '',
      'description'   => $abstract,
      'content'       => $content,
      'url'           => $item['url']    ?? '',
      'image_url'     => $imageUrl,
      'author'        => trim(str_replace('By ', '', $item['byline'] ?? '')) ?: null,
      'source_name'   => 'The New York Times',
      'category'      => $category,
      'published_at'  => Carbon::parse($publishedAt)->utc(),
      'fetched_at'    => now(),
    ];
  }

  private function joinFacets(string $label, array $facets): string
  {
    if (empty($facets)) {
      return '';
    }

    return $label . ': ' . implode(', ', $facets);
  }

  public function getSourceName(): string
  {
    return 'The New York Times';
  }

  public function getSourceId(): string
  {
    return 'nytimes';
  }
}
