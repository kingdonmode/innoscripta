<?php

namespace Tests\Feature;

use App\Contracts\NewsFetcherInterface;
use App\Jobs\FetchSourceArticles;
use App\Services\News\NewsAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class FetchNewsArticlesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeFetcher(string $sourceId, int $articleCount = 2): NewsFetcherInterface
    {
        $articles = collect(array_fill(0, $articleCount, []));

        $mock = Mockery::mock(NewsFetcherInterface::class);
        $mock->shouldReceive('getSourceId')->andReturn($sourceId);
        $mock->shouldReceive('getSourceName')->andReturn(ucfirst($sourceId));
        $mock->shouldReceive('fetch')->andReturn($articles);

        return $mock;
    }

    private function mockAggregator(array $fetchers, array $summary = []): void
    {
        $aggregator = Mockery::mock(NewsAggregatorService::class);
        $aggregator->shouldReceive('getFetchers')->andReturn($fetchers);

        if (!empty($summary)) {
            $aggregator->shouldReceive('fetchAndStore')->andReturn($summary);
        }

        $this->app->instance(NewsAggregatorService::class, $aggregator);
    }

    // -------------------------------------------------------------------------
    // Default mode — jobs dispatched
    // -------------------------------------------------------------------------

    public function test_default_mode_dispatches_one_job_per_source(): void
    {
        Queue::fake();

        $fetchers = [
            $this->makeFetcher('newsapi'),
            $this->makeFetcher('guardian'),
            $this->makeFetcher('nytimes'),
        ];

        $this->mockAggregator($fetchers);

        $this->artisan('news:fetch')->assertSuccessful();

        Queue::assertCount(3);
        Queue::assertPushed(FetchSourceArticles::class);
    }

    public function test_default_mode_dispatches_jobs_with_correct_options(): void
    {
        Queue::fake();

        $this->mockAggregator([$this->makeFetcher('newsapi')]);

        $this->artisan('news:fetch', ['--query' => 'science', '--section' => 'world'])
             ->assertSuccessful();

        Queue::assertPushed(FetchSourceArticles::class, function (FetchSourceArticles $job) {
            $options = (new \ReflectionProperty($job, 'options'))->getValue($job);
            return $options['query'] === 'science' && $options['section'] === 'world';
        });
    }

    public function test_default_mode_outputs_dispatched_confirmation(): void
    {
        Queue::fake();

        $this->mockAggregator([$this->makeFetcher('newsapi')]);

        $this->artisan('news:fetch')
             ->expectsOutputToContain('job dispatched')
             ->expectsOutputToContain('queue:work')
             ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --sync mode
    // -------------------------------------------------------------------------

    public function test_sync_mode_does_not_dispatch_jobs(): void
    {
        Queue::fake();

        $this->mockAggregator(
            fetchers: [$this->makeFetcher('newsapi')],
            summary:  ['newsapi' => 10],
        );

        $this->artisan('news:fetch', ['--sync' => true])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_sync_mode_outputs_articles_saved_per_source(): void
    {
        Queue::fake();

        $this->mockAggregator(
            fetchers: [$this->makeFetcher('newsapi'), $this->makeFetcher('guardian')],
            summary:  ['newsapi' => 30, 'guardian' => 25],
        );

        $this->artisan('news:fetch', ['--sync' => true])
             ->expectsOutputToContain('30 articles saved')
             ->expectsOutputToContain('25 articles saved')
             ->expectsOutputToContain('55 total articles persisted')
             ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --dry-run mode
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_dispatch_jobs(): void
    {
        Queue::fake();

        $this->mockAggregator([$this->makeFetcher('newsapi', 5)]);

        $this->artisan('news:fetch', ['--dry-run' => true])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_dry_run_outputs_fetched_count_without_saving(): void
    {
        Queue::fake();

        $this->mockAggregator([$this->makeFetcher('newsapi', 7)]);

        $this->artisan('news:fetch', ['--dry-run' => true])
             ->expectsOutputToContain('[dry-run]')
             ->expectsOutputToContain('7 articles fetched')
             ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
