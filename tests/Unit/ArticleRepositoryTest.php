<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Repositories\ArticleRepository;
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

    public function test_upsert_batch_inserts_new_articles(): void
    {
        $articles = Article::factory()->count(2)->make()->map(fn(Article $a) => $a->toArray());

        $saved = $this->repository->upsertBatch($articles);

        $this->assertSame(2, $saved);
        $this->assertSame(2, Article::count());
    }

    public function test_upsert_batch_updates_existing_article_on_url_conflict(): void
    {
        $sharedUrl = 'https://example.com/same-article';

        $original = Article::factory()->make(['url' => $sharedUrl, 'title' => 'Old title'])->toArray();
        $this->repository->upsertBatch(collect([$original]));

        $updated = Article::factory()->make(['url' => $sharedUrl, 'title' => 'Updated title'])->toArray();
        $this->repository->upsertBatch(collect([$updated]));

        $this->assertSame(1, Article::count());
        $this->assertSame('Updated title', Article::first()->title);
    }

    public function test_upsert_batch_skips_articles_missing_url(): void
    {
        $bad  = Article::factory()->make(['url' => ''])->toArray();
        $good = Article::factory()->make()->toArray();

        $saved = $this->repository->upsertBatch(collect([$bad, $good]));

        $this->assertSame(1, $saved);
        $this->assertSame(1, Article::count());
    }

    public function test_upsert_batch_skips_articles_missing_title(): void
    {
        $bad = Article::factory()->make(['title' => ''])->toArray();

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
        $article = Article::factory()->make(['title' => str_repeat('x', 300)])->toArray();

        $this->repository->upsertBatch(collect([$article]));

        $this->assertSame(255, mb_strlen(Article::first()->title));
    }
}
