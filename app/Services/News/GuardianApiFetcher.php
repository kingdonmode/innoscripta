<?php

namespace App\Services\News;

use App\Contracts\NewsFetcherInterface;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Guardian\GuardianAPI;

class GuardianApiFetcher implements NewsFetcherInterface
{
  protected string $apiKey;
  protected GuardianAPI $api;

  public function __construct()
  {
    $this->apiKey = config('services.guardianapi.key');

    if (empty($this->apiKey)) {
      throw new \RuntimeException('Guardian API key is missing in config/services.php');
    }

    $this->api = new GuardianAPI($this->apiKey);
  }

  public function fetch(array $options = []): Collection
  {
    $defaultOptions = [
      'query'     => $options['query'] ?? 'technology',
      'from'      => $options['from'] ?? Carbon::now()->subDay(),
      'to'        => $options['to']   ?? Carbon::now(),
      'pageSize'  => $options['pageSize'] ?? 30,
      'page'      => 1,
      'section'   => null,
      'tag'       => null,
      'orderBy'   => 'newest',
    ];

    $options = array_merge($defaultOptions, $options);

    // Enforce reasonable limits (Guardian allows up to 200 per page in some tiers)
    $pageSize = min(max(1, (int) $options['pageSize']), 50);

    try {
      $content = $this->api->content();

      // Required / common filters
      $content->setQuery($options['query']);
      $content->setPageSize($pageSize);
      $content->setPage($options['page']);
      $content->setOrderBy($options['orderBy']);

      if ($options['from'] instanceof Carbon) {
        $content->setFromDate($options['from']->toDateTimeImmutable());
      } elseif ($options['from']) {
        $content->setFromDate(Carbon::parse($options['from'])->toDateTimeImmutable());
      }

      if ($options['to'] instanceof Carbon) {
        $content->setToDate($options['to']->toDateTimeImmutable());
      } elseif ($options['to']) {
        $content->setToDate(Carbon::parse($options['to'])->toDateTimeImmutable());
      }

      if (!empty($options['section']) && $options['section'] !== 'all') {
        $content->setSection($options['section']);
      }
      if ($options['tag']) {
        $content->setTag($options['tag']);
      }

      $content->setShowFields('headline,standfirst,trailText,byline,thumbnail,shortUrl,bodyText');
      $content->setShowTags('contributor');

      $response = $content->fetch();

      $inner  = $response->response ?? null;
      $status = $inner->status ?? 'error';

      if ($status !== 'ok' || $inner === null) {
        Log::error('Guardian API fetch failed', ['response' => (array) $response]);
        return collect();
      }

      $articles = $inner->results ?? [];

      return collect($articles)->map(fn($item) => $this->normalize((array) $item));
    } catch (\Exception $e) {
      Log::error('Guardian API request exception', [
        'error' => $e->getMessage(),
        'options' => $options,
      ]);
      return collect();
    }
  }

  public function normalize(array $item): array
  {
    $raw    = $item['fields'] ?? [];
    $fields = $raw instanceof \stdClass ? (array) $raw : (array) $raw;

    $publishedAt = $item['webPublicationDate'] ?? now()->toIso8601ZuluString();

    $author = $fields['byline'] ?? null;
    if (!$author && !empty($item['tags'])) {
      $contributorTags = collect($item['tags'])->where('type', 'contributor')->pluck('webTitle')->implode(', ');
      $author = $contributorTags ?: null;
    }

    return [
      'external_id'   => md5($item['id'] ?? $item['webUrl'] ?? uniqid('guardian_', true)),
      'source'        => $this->getSourceId(),
      'title'         => $fields['headline'] ?? $item['webTitle'] ?? '',
      'description'   => $fields['trailText'] ?? $fields['standfirst'] ?? '',
      'content'       => $fields['bodyText'] ?? '',
      'url'           => $item['webUrl'] ?? ($fields['shortUrl'] ?? ''),
      'image_url'     => $fields['thumbnail'] ?? null,
      'author'        => $author,
      'source_name'   => 'The Guardian',
      'category'      => $item['sectionName'] ?? ($item['pillarName'] ?? null),
      'published_at'  => Carbon::parse($publishedAt)->utc(),
      'fetched_at'    => now(),
    ];
  }

  public function getSourceName(): string
  {
    return 'The Guardian';
  }

  public function getSourceId(): string
  {
    return 'guardian';
  }
}
