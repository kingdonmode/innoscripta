<?php

namespace App\Services\News;

use App\Contracts\NewsFetcherInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NewsApiFetcher implements NewsFetcherInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://eventregistry.org/api/v1/article/getArticles';

    public function __construct()
    {
        $this->apiKey = config('services.newsapi.key');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('NewsAPI key is missing in config/services.php');
        }
    }

    public function fetch(array $options = []): Collection
    {
        $from = $options['from'] ?? Carbon::now()->subDay();
        $to   = $options['to']   ?? null;

        $articlesCount = min(max(1, (int) ($options['pageSize'] ?? 30)), 100);

        $queryParams = [
            'apiKey'                   => $this->apiKey,
            'action'                   => 'getArticles',
            'keyword'                  => $options['query'] ?? 'technology',
            'lang'                     => 'eng',
            'articlesCount'            => $articlesCount,
            'articlesSortBy'           => 'date',
            'resultType'               => 'articles',
            'dateStart'                => ($from instanceof Carbon ? $from : Carbon::parse($from))->format('Y-m-d'),
            'includeArticleCategories' => true,
        ];

        if ($to) {
            $queryParams['dateEnd'] = ($to instanceof Carbon ? $to : Carbon::parse($to))->format('Y-m-d');
        }

        try {
            $response = Http::get($this->baseUrl, $queryParams);

            if (!$response->successful()) {
                Log::error('EventRegistry fetch failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return collect();
            }

            $data     = $response->json();
            $articles = $data['articles']['results'] ?? [];

            return collect($articles)->map(fn(array $item) => $this->normalize($item));
        } catch (\Exception $e) {
            Log::error('EventRegistry request exception', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    public function normalize(array $item): array
    {
        $publishedAt = $item['dateTimePub'] ?? $item['dateTime'] ?? now()->toIso8601ZuluString();

        $author = null;
        if (!empty($item['authors']) && is_array($item['authors'])) {
            $author = $item['authors'][0]['name'] ?? null;
        }

        $category = null;
        if (!empty($item['categories']) && is_array($item['categories'])) {
            $raw      = $item['categories'][0]['label'] ?? null;
            $category = $raw ? trim(last(explode('/', $raw))) : null;
        }

        $body = $item['body'] ?? '';

        // Derive a short description from the opening sentence when no dedicated field exists.
        $description = '';
        if ($body) {
            $firstSentence = strstr($body, '. ', true);
            $description   = $firstSentence ? $firstSentence . '.' : mb_substr($body, 0, 250);
        }

        return [
            'external_id'  => md5($item['url'] ?? uniqid('newsapi_', true)),
            'source'       => $this->getSourceId(),
            'title'        => $item['title']            ?? '',
            'description'  => $description,
            'content'      => $body,
            'url'          => $item['url']              ?? '',
            'image_url'    => $item['image']            ?? null,
            'author'       => $author,
            'source_name'  => 'News Api',
            'category'     => $category,
            'published_at' => Carbon::parse($publishedAt)->utc(),
            'fetched_at'   => now(),
        ];
    }

    public function getSourceName(): string
    {
        return 'NewsAPI';
    }

    public function getSourceId(): string
    {
        return 'newsapi';
    }
}
