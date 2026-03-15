<?php

namespace Tests\Unit;

use App\Contracts\NewsFetcherInterface;
use App\Jobs\FetchSourceArticles;
use App\Repositories\ArticleRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchSourceArticlesJobTest extends TestCase
{
    use RefreshDatabase;

    private function sampleArticle(string $sourceId): array
    {
        return [
            'external_id'  => md5(uniqid()),
            'source'    => $sourceId,
            'title'        => 'Test headline',
            'description'  => 'Summary text.',
            'content'      => 'Full body.',
            'url'          => 'https://example.com/' . uniqid(),
            'image_url'    => null,
            'author'       => null,
            'category'     => null,
            'source_name'  => ucfirst($sourceId),
            'published_at' => Carbon::now(),
        ];
    }

    private function bindFetcher(string $sourceId, int $count, array $options = []): string
    {
        $articles = collect(array_map(fn() => $this->sampleArticle($sourceId), range(1, $count)));

        $fetcher = Mockery::mock(NewsFetcherInterface::class);
        $fetcher->shouldReceive('fetch')->with($options)->andReturn($articles);
        $fetcher->shouldReceive('getSourceId')->andReturn($sourceId);

        $class = "FakeFetcher_{$sourceId}";
        $this->app->bind($class, fn() => $fetcher);

        return $class;
    }

    // -------------------------------------------------------------------------
    // handle()
    // -------------------------------------------------------------------------

    public function test_handle_fetches_and_persists_articles(): void
    {
        $class = $this->bindFetcher('newsapi', 3);

        $job = new FetchSourceArticles($class, []);
        $job->handle(new ArticleRepository());

        $this->assertDatabaseCount('articles', 3);
    }

    public function test_handle_passes_options_to_fetcher(): void
    {
        $options = ['query' => 'climate', 'section' => 'world'];
        $class   = $this->bindFetcher('guardian', 2, $options);

        $job = new FetchSourceArticles($class, $options);
        $job->handle(new ArticleRepository());

        $this->assertDatabaseCount('articles', 2);
    }

    public function test_handle_records_are_queryable_after_persist(): void
    {
        $class = $this->bindFetcher('nytimes', 2);

        $job = new FetchSourceArticles($class, []);
        $job->handle(new ArticleRepository());

        $this->assertDatabaseHas('articles', ['source' => 'nytimes']);
        $this->assertDatabaseCount('articles', 2);
    }

    public function test_handle_saves_nothing_when_fetcher_returns_empty(): void
    {
        $fetcher = Mockery::mock(NewsFetcherInterface::class);
        $fetcher->shouldReceive('fetch')->andReturn(collect());
        $fetcher->shouldReceive('getSourceId')->andReturn('newsapi');

        $this->app->bind('EmptyFetcher', fn() => $fetcher);

        $job = new FetchSourceArticles('EmptyFetcher', []);
        $job->handle(new ArticleRepository());

        $this->assertDatabaseCount('articles', 0);
    }

    // -------------------------------------------------------------------------
    // Retry configuration
    // -------------------------------------------------------------------------

    public function test_job_has_three_max_tries(): void
    {
        $job = new FetchSourceArticles('SomeClass', []);

        $this->assertSame(3, $job->tries);
    }

    public function test_job_has_sixty_second_backoff(): void
    {
        $job = new FetchSourceArticles('SomeClass', []);

        $this->assertSame(60, $job->backoff);
    }

    // -------------------------------------------------------------------------
    // failed()
    // -------------------------------------------------------------------------

    public function test_failed_does_not_throw(): void
    {
        $job = new FetchSourceArticles('MyFetcherClass', []);

        // failed() must never rethrow — it is a terminal handler called by the queue
        $this->expectNotToPerformAssertions();
        $job->failed(new \RuntimeException('API timeout'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
