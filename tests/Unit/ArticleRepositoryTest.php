<?php

namespace Tests\Unit;

use App\DTOs\ArticleDto;
use App\Models\Article;
use App\Repositories\ArticleRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ArticleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ArticleRepository();
    }

    private function sampleDto(array $overrides = []): ArticleDto
    {
        return new ArticleDto(
            externalId:  $overrides['externalId']  ?? md5(uniqid()),
            source:      $overrides['source']      ?? 'newsapi',
            title:       $overrides['title']       ?? 'Test headline',
            description: $overrides['description'] ?? 'Short summary.',
            content:     $overrides['content']     ?? 'Full body text.',
            url:         $overrides['url']         ?? 'https://example.com/' . uniqid(),
            imageUrl:    $overrides['imageUrl']    ?? null,
            author:      $overrides['author']      ?? 'Reporter Name',
            category:    $overrides['category']    ?? 'technology',
            sourceName:  $overrides['sourceName']  ?? 'NewsAPI',
            publishedAt: $overrides['publishedAt'] ?? Carbon::now()->subMinutes(30),
        );
    }

    public function test_upsert_batch_inserts_new_articles(): void
    {
        $articles = collect([$this->sampleDto(), $this->sampleDto()]);

        $saved = $this->repository->upsertBatch($articles);

        $this->assertSame(2, $saved);
        $this->assertSame(2, Article::count());
    }

    public function test_upsert_batch_updates_existing_article_on_url_conflict(): void
    {
        $sharedUrl = 'https://example.com/same-article';

        $this->repository->upsertBatch(collect([$this->sampleDto(['url' => $sharedUrl, 'title' => 'Old title'])]));
        $this->repository->upsertBatch(collect([$this->sampleDto(['url' => $sharedUrl, 'title' => 'Updated title'])]));

        $this->assertSame(1, Article::count());
        $this->assertSame('Updated title', Article::first()->title);
    }

    public function test_upsert_batch_skips_articles_missing_url(): void
    {
        $bad  = $this->sampleDto(['url' => '']);
        $good = $this->sampleDto();

        $saved = $this->repository->upsertBatch(collect([$bad, $good]));

        $this->assertSame(1, $saved);
        $this->assertSame(1, Article::count());
    }

    public function test_upsert_batch_skips_articles_missing_title(): void
    {
        $bad = $this->sampleDto(['title' => '']);

        $saved = $this->repository->upsertBatch(collect([$bad]));

        $this->assertSame(0, $saved);
        $this->assertSame(0, Article::count());
    }

    public function test_upsert_batch_returns_zero_for_empty_collection(): void
    {
        $saved = $this->repository->upsertBatch(collect([]));

        $this->assertSame(0, $saved);
    }

    public function test_upsert_batch_truncates_oversized_title(): void
    {
        $article = $this->sampleDto(['title' => str_repeat('x', 300)]);

        $this->repository->upsertBatch(collect([$article]));

        $this->assertSame(255, mb_strlen(Article::first()->title));
    }
}
