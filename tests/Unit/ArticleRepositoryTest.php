<?php

namespace Tests\Unit;

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

    private function sampleArticle(array $overrides = []): array
    {
        return array_merge([
            'external_id'  => md5(uniqid()),
            'source_id'    => 'newsapi',
            'title'        => 'Test headline',
            'description'  => 'Short summary.',
            'content'      => 'Full body text.',
            'url'          => 'https://example.com/' . uniqid(),
            'image_url'    => null,
            'author'       => 'Reporter Name',
            'category'     => 'technology',
            'source_name'  => 'NewsAPI',
            'published_at' => Carbon::now()->subMinutes(30),
        ], $overrides);
    }

    public function test_upsert_batch_inserts_new_articles(): void
    {
        $articles = collect([$this->sampleArticle(), $this->sampleArticle()]);

        $saved = $this->repository->upsertBatch($articles);

        $this->assertSame(2, $saved);
        $this->assertSame(2, Article::count());
    }

    public function test_upsert_batch_updates_existing_article_on_url_conflict(): void
    {
        $sharedUrl = 'https://example.com/same-article';

        $original = $this->sampleArticle(['url' => $sharedUrl, 'title' => 'Old title']);
        $this->repository->upsertBatch(collect([$original]));

        $updated = $this->sampleArticle(['url' => $sharedUrl, 'title' => 'Updated title']);
        $this->repository->upsertBatch(collect([$updated]));

        $this->assertSame(1, Article::count());
        $this->assertSame('Updated title', Article::first()->title);
    }

    public function test_upsert_batch_skips_articles_missing_url(): void
    {
        $bad  = $this->sampleArticle(['url' => '']);
        $good = $this->sampleArticle();

        $saved = $this->repository->upsertBatch(collect([$bad, $good]));

        $this->assertSame(1, $saved);
        $this->assertSame(1, Article::count());
    }

    public function test_upsert_batch_skips_articles_missing_title(): void
    {
        $bad = $this->sampleArticle(['title' => '']);

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
        $article = $this->sampleArticle(['title' => str_repeat('x', 300)]);

        $this->repository->upsertBatch(collect([$article]));

        $this->assertSame(255, mb_strlen(Article::first()->title));
    }
}
