<?php

namespace App\Jobs;

use App\Contracts\NewsFetcherInterface;
use App\Repositories\ArticleRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchSourceArticles implements ShouldQueue
{
    use Queueable;

    /**
     * Retry up to 3 times before marking the job as failed.
     * Each retry backs off by (attempt * 60) seconds.
     */
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly string $fetcherClass,
        private readonly array  $options = [],
    ) {}

    public function handle(ArticleRepository $repository): void
    {
        /** @var NewsFetcherInterface $fetcher */
        $fetcher  = app($this->fetcherClass);
        $articles = $fetcher->fetch($this->options);
        $saved    = $repository->upsertBatch($articles);

        Log::info("FetchSourceArticles: {$fetcher->getSourceId()} — {$saved} articles saved");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("FetchSourceArticles failed for [{$this->fetcherClass}]", [
            'error' => $e->getMessage(),
        ]);
    }
}
