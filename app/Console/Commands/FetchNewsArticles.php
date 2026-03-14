<?php

namespace App\Console\Commands;

use App\Services\News\NewsAggregatorService;
use Illuminate\Console\Command;

class FetchNewsArticles extends Command
{
    protected $signature = 'news:fetch
                            {--query=technology : Keyword to search for}
                            {--section=all      : NYTimes section (all, world, technology, etc.)}
                            {--dry-run          : Fetch articles but do not persist them}';

    protected $description = 'Fetch latest articles from all configured news sources and store them';

    public function handle(NewsAggregatorService $aggregator): int
    {
        $options = [
            'query'   => $this->option('query'),
            'section' => $this->option('section'),
        ];

        $this->info('Fetching articles from all news sources...');

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] No articles will be persisted.');

            foreach ($aggregator->getFetchers() as $fetcher) {
                $articles = $fetcher->fetch($options);
                $this->line(sprintf(
                    '  %-12s → %d articles fetched',
                    $fetcher->getSourceId(),
                    $articles->count()
                ));
            }

            return self::SUCCESS;
        }

        $summary = $aggregator->fetchAndStore($options);

        $total = 0;
        foreach ($summary as $source => $count) {
            $this->line(sprintf('  %-12s → %d articles saved', $source, $count));
            $total += $count;
        }

        $this->info("Done. {$total} total articles persisted.");

        return self::SUCCESS;
    }
}
