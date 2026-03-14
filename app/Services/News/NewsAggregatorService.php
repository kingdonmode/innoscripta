<?php

namespace App\Services\News;

use App\Contracts\NewsFetcherInterface;
use App\Repositories\ArticleRepository;
use Illuminate\Support\Facades\Log;

class NewsAggregatorService
{
    /** @param NewsFetcherInterface[] $fetchers */
    public function __construct(
        private readonly array $fetchers,
        private readonly ArticleRepository $repository,
    ) {}

    /**
     * Run all registered fetchers and persist the results.
     *
     * @return array<string, int>  Map of source_id => articles saved
     */
    public function fetchAndStore(array $options = []): array
    {
        $summary = [];

        foreach ($this->fetchers as $fetcher) {
            $sourceId = $fetcher->getSourceId();

            try {
                $articles = $fetcher->fetch($options);
                $saved    = $this->repository->upsertBatch($articles);
                $summary[$sourceId] = $saved;

                Log::info("NewsAggregator: fetched from {$sourceId}", [
                    'fetched' => $articles->count(),
                    'saved'   => $saved,
                ]);
            } catch (\Throwable $e) {
                $summary[$sourceId] = 0;

                Log::error("NewsAggregator: error fetching from {$sourceId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /** @return NewsFetcherInterface[] */
    public function getFetchers(): array
    {
        return $this->fetchers;
    }
}
