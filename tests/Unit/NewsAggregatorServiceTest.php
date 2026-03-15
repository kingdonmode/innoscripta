<?php

namespace Tests\Unit;

use App\Contracts\NewsFetcherInterface;
use App\DTOs\ArticleDto;
use App\Repositories\ArticleRepository;
use App\Services\News\NewsAggregatorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class NewsAggregatorServiceTest extends TestCase
{
    private function makeFetcherMock(string $sourceId, Collection $articles): NewsFetcherInterface
    {
        $mock = Mockery::mock(NewsFetcherInterface::class);
        $mock->shouldReceive('getSourceId')->andReturn($sourceId);
        $mock->shouldReceive('fetch')->andReturn($articles);

        return $mock;
    }

    private function makeDto(string $source): ArticleDto
    {
        return new ArticleDto(
            externalId:  md5(uniqid()),
            source:      $source,
            title:       'Headline',
            description: 'Summary',
            content:     null,
            url:         'https://example.com/' . uniqid(),
            imageUrl:    null,
            author:      null,
            category:    null,
            sourceName:  ucfirst($source),
            publishedAt: Carbon::now(),
        );
    }

    public function test_fetch_and_store_aggregates_all_fetchers(): void
    {
        $fetcher1 = $this->makeFetcherMock('newsapi', collect([$this->makeDto('newsapi')]));
        $fetcher2 = $this->makeFetcherMock('guardian', collect([$this->makeDto('guardian'), $this->makeDto('guardian')]));

        $repository = Mockery::mock(ArticleRepository::class);
        $repository->shouldReceive('upsertBatch')->twice()->andReturn(1, 2);

        $service = new NewsAggregatorService([$fetcher1, $fetcher2], $repository);
        $summary = $service->fetchAndStore();

        $this->assertSame(['newsapi' => 1, 'guardian' => 2], $summary);
    }

    public function test_fetch_and_store_handles_fetcher_exception_gracefully(): void
    {
        $failingFetcher = Mockery::mock(NewsFetcherInterface::class);
        $failingFetcher->shouldReceive('getSourceId')->andReturn('broken');
        $failingFetcher->shouldReceive('fetch')->andThrow(new \RuntimeException('API down'));

        $repository = Mockery::mock(ArticleRepository::class);
        $repository->shouldReceive('upsertBatch')->never();

        $service = new NewsAggregatorService([$failingFetcher], $repository);
        $summary = $service->fetchAndStore();

        $this->assertSame(['broken' => 0], $summary);
    }

    public function test_get_fetchers_returns_all_registered_fetchers(): void
    {
        $f1 = $this->makeFetcherMock('newsapi', collect());
        $f2 = $this->makeFetcherMock('nytimes', collect());

        $repository = Mockery::mock(ArticleRepository::class);
        $service    = new NewsAggregatorService([$f1, $f2], $repository);

        $this->assertCount(2, $service->getFetchers());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
