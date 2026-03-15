<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'external_id'  => md5(uniqid()),
            'source_id'    => 'newsapi',
            'title'        => 'Sample article title',
            'description'  => 'A short description.',
            'content'      => 'Full article content here.',
            'url'          => 'https://example.com/' . uniqid(),
            'image_url'    => null,
            'author'       => 'Jane Doe',
            'category'     => 'technology',
            'source_name'  => 'NewsAPI',
            'published_at' => now()->subHour(),
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // GET /api/articles
    // -------------------------------------------------------------------------

    public function test_articles_index_returns_paginated_json(): void
    {
        $this->makeArticle();
        $this->makeArticle(['title' => 'Another piece']);

        $response = $this->getJson('/api/v1/articles');

        $response->assertOk()
                 ->assertJsonStructure([
                     'data'         => [['id', 'title', 'source_id', 'published_at']],
                     'current_page',
                     'per_page',
                     'total',
                 ]);
    }

    public function test_articles_index_returns_empty_when_no_records(): void
    {
        $response = $this->getJson('/api/v1/articles');

        $response->assertOk()
                 ->assertJsonPath('total', 0);
    }

    public function test_search_filters_by_title_keyword(): void
    {
        $this->makeArticle(['title' => 'Laravel is fantastic']);
        $this->makeArticle(['title' => 'Vue.js guide']);

        $response = $this->getJson('/api/v1/articles?q=Laravel');

        $response->assertOk()
                 ->assertJsonPath('total', 1)
                 ->assertJsonFragment(['title' => 'Laravel is fantastic']);
    }

    public function test_search_filters_by_description_keyword(): void
    {
        $this->makeArticle(['description' => 'Deep dive into Redis caching']);
        $this->makeArticle(['description' => 'Intro to containers']);

        $response = $this->getJson('/api/v1/articles?q=Redis');

        $response->assertOk()
                 ->assertJsonPath('total', 1);
    }

    public function test_filter_by_single_source(): void
    {
        $this->makeArticle(['source_id' => 'guardian']);
        $this->makeArticle(['source_id' => 'nytimes']);

        $response = $this->getJson('/api/v1/articles?source=guardian');

        $response->assertOk()
                 ->assertJsonPath('total', 1)
                 ->assertJsonFragment(['source_id' => 'guardian']);
    }

    public function test_filter_by_multiple_sources(): void
    {
        $this->makeArticle(['source_id' => 'guardian']);
        $this->makeArticle(['source_id' => 'nytimes']);
        $this->makeArticle(['source_id' => 'newsapi']);

        $response = $this->getJson('/api/v1/articles?source=guardian,nytimes');

        $response->assertOk()
                 ->assertJsonPath('total', 2);
    }

    public function test_filter_by_category(): void
    {
        $this->makeArticle(['category' => 'technology']);
        $this->makeArticle(['category' => 'sports']);

        $response = $this->getJson('/api/v1/articles?category=sports');

        $response->assertOk()
                 ->assertJsonPath('total', 1)
                 ->assertJsonFragment(['category' => 'sports']);
    }

    public function test_filter_by_single_author(): void
    {
        $this->makeArticle(['author' => 'Alice Smith']);
        $this->makeArticle(['author' => 'Bob Jones']);

        $response = $this->getJson('/api/v1/articles?author=Alice+Smith');

        $response->assertOk()
                 ->assertJsonPath('total', 1)
                 ->assertJsonFragment(['author' => 'Alice Smith']);
    }

    public function test_filter_by_multiple_authors(): void
    {
        $this->makeArticle(['author' => 'Alice Smith']);
        $this->makeArticle(['author' => 'Bob Jones']);
        $this->makeArticle(['author' => 'Carol White']);

        $response = $this->getJson('/api/v1/articles?author=Alice+Smith,Bob+Jones');

        $response->assertOk()
                 ->assertJsonPath('total', 2);
    }

    public function test_filter_by_author_partial_name_returns_no_results(): void
    {
        $this->makeArticle(['author' => 'Alice Smith']);

        $response = $this->getJson('/api/v1/articles?author=Alice');

        $response->assertOk()
                 ->assertJsonPath('total', 0);
    }

    public function test_authors_returns_distinct_non_null_authors(): void
    {
        $this->makeArticle(['author' => 'Alice Smith']);
        $this->makeArticle(['author' => 'Alice Smith']);
        $this->makeArticle(['author' => 'Bob Jones']);
        $this->makeArticle(['author' => null]);

        $response = $this->getJson('/api/v1/articles/authors');

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    public function test_filter_by_date_range(): void
    {
        $this->makeArticle(['published_at' => '2024-01-10 12:00:00']);
        $this->makeArticle(['published_at' => '2024-06-15 12:00:00']);
        $this->makeArticle(['published_at' => '2024-12-20 12:00:00']);

        $response = $this->getJson('/api/v1/articles?from=2024-01-01&to=2024-07-01');

        $response->assertOk()
                 ->assertJsonPath('total', 2);
    }

    public function test_per_page_param_controls_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeArticle();
        }

        $response = $this->getJson('/api/v1/articles?per_page=2');

        $response->assertOk()
                 ->assertJsonPath('per_page', 2)
                 ->assertJsonCount(2, 'data');
    }

    public function test_invalid_per_page_returns_422(): void
    {
        $response = $this->getJson('/api/v1/articles?per_page=999');

        $response->assertUnprocessable();
    }

    public function test_invalid_date_returns_422(): void
    {
        $response = $this->getJson('/api/v1/articles?from=not-a-date');

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/{id}
    // -------------------------------------------------------------------------

    public function test_show_returns_single_article(): void
    {
        $article = $this->makeArticle(['title' => 'Specific article']);

        $response = $this->getJson("/api/v1/articles/{$article->id}");

        $response->assertOk()
                 ->assertJsonPath('data.id', $article->id)
                 ->assertJsonPath('data.title', 'Specific article');
    }

    public function test_show_returns_404_for_missing_article(): void
    {
        $response = $this->getJson('/api/v1/articles/99999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/sources
    // -------------------------------------------------------------------------

    public function test_sources_returns_distinct_sources(): void
    {
        $this->makeArticle(['source_id' => 'newsapi', 'source_name' => 'NewsAPI']);
        $this->makeArticle(['source_id' => 'newsapi', 'source_name' => 'NewsAPI']);
        $this->makeArticle(['source_id' => 'guardian', 'source_name' => 'The Guardian']);

        $response = $this->getJson('/api/v1/articles/sources');

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/categories
    // -------------------------------------------------------------------------

    public function test_categories_returns_distinct_non_null_categories(): void
    {
        $this->makeArticle(['category' => 'technology']);
        $this->makeArticle(['category' => 'technology']);
        $this->makeArticle(['category' => 'world']);
        $this->makeArticle(['category' => null]);

        $response = $this->getJson('/api/v1/articles/categories');

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }
}
