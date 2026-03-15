<?php

namespace App\Console\Commands;

use App\Jobs\FetchSourceArticles;
use App\Services\News\NewsAggregatorService;
use Illuminate\Console\Command;

use function get_class;
use function sprintf;

class FetchNewsArticles extends Command
{
    protected $signature = 'news:fetch
                            {--query=technology : Keyword to search for}
                            {--section=all      : NYTimes section (all, world, technology, etc.)}
                            {--dry-run          : Fetch articles but do not persist them}
                            {--sync             : Run synchronously instead of queuing jobs}';

    protected $description = 'Dispatch jobs to fetch articles from all configured news sources';

    public function handle(NewsAggregatorService $aggregator): int
    {
        $options = [
            'query'   => $this->option('query'),
            'section' => $this->option('section'),
        ];

        if ($this->option('dry-run')) {
            return $this->runDryRun($aggregator, $options);
        }

        if ($this->option('sync')) {
            return $this->runSync($aggregator, $options);
        }

        return $this->dispatchJobs($aggregator, $options);
    }

    private function runDryRun(NewsAggregatorService $aggregator, array $options): int
    {
        $this->warn('[dry-run] No articles will be persisted.');

        foreach ($aggregator->getFetchers() as $fetcher) {
            $count = $fetcher->fetch($options)->count();
            $this->line(sprintf('  %-12s → %d articles fetched', $fetcher->getSourceId(), $count));
        }

        return self::SUCCESS;
    }

    private function runSync(NewsAggregatorService $aggregator, array $options): int
    {
        $total = 0;

        foreach ($aggregator->fetchAndStore($options) as $source => $count) {
            $this->line(sprintf('  %-12s → %d articles saved', $source, $count));
            $total += $count;
        }

        $this->info("Done. {$total} total articles persisted.");

        return self::SUCCESS;
    }

    private function dispatchJobs(NewsAggregatorService $aggregator, array $options): int
    {
        foreach ($aggregator->getFetchers() as $fetcher) {
            FetchSourceArticles::dispatch(get_class($fetcher), $options);
            $this->line(sprintf('  %-12s → job dispatched', $fetcher->getSourceId()));
        }

        $this->info('All fetch jobs queued. Run [php artisan queue:work] to process them.');

        return self::SUCCESS;
    }
}
