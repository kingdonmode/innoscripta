<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/v1/articles
    // -------------------------------------------------------------------------

    public function test_articles_index_returns_paginated_json(): void
    {
        Article::factory()->count(2)->create();

        $response = $this->getJson(route('v1.articles.index'));

        $response->assertOk()
                 ->assertJsonStructure([
                     'data' => [['id', 'title', 'source', 'published_at']],
                     'meta' => ['current_page', 'per_page', 'total'],
                 ]);
    }

    public function test_articles_index_returns_empty_when_no_records(): void
    {
        $response = $this->getJson(route('v1.articles.index'));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 0);
    }

    public function test_search_filters_by_title_keyword(): void
    {
        Article::factory()->create(['title' => 'Laravel is fantastic']);
        Article::factory()->create(['title' => 'Vue.js guide']);

        $response = $this->getJson(route('v1.articles.index', ['q' => 'Laravel']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1)
                 ->assertJsonFragment(['title' => 'Laravel is fantastic']);
    }

    public function test_search_filters_by_description_keyword(): void
    {
        Article::factory()->create(['description' => 'Deep dive into Redis caching']);
        Article::factory()->create(['description' => 'Intro to containers']);

        $response = $this->getJson(route('v1.articles.index', ['q' => 'Redis']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_by_single_source(): void
    {
        Article::factory()->create(['source' => 'guardian']);
        Article::factory()->create(['source' => 'nytimes']);

        $response = $this->getJson(route('v1.articles.index', ['source' => 'guardian']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1)
                 ->assertJsonFragment(['source' => 'guardian']);
    }

    public function test_filter_by_multiple_sources(): void
    {
        Article::factory()->create(['source' => 'guardian']);
        Article::factory()->create(['source' => 'nytimes']);
        Article::factory()->create(['source' => 'newsapi']);

        $response = $this->getJson(route('v1.articles.index', ['source' => 'guardian,nytimes']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_by_category(): void
    {
        Article::factory()->create(['category' => 'technology']);
        Article::factory()->create(['category' => 'sports']);

        $response = $this->getJson(route('v1.articles.index', ['category' => 'sports']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1)
                 ->assertJsonFragment(['category' => 'sports']);
    }

    public function test_filter_by_single_author(): void
    {
        Article::factory()->create(['author' => 'Alice Smith']);
        Article::factory()->create(['author' => 'Bob Jones']);

        $response = $this->getJson(route('v1.articles.index', ['author' => 'Alice Smith']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1)
                 ->assertJsonFragment(['author' => 'Alice Smith']);
    }

    public function test_filter_by_multiple_authors(): void
    {
        Article::factory()->create(['author' => 'Alice Smith']);
        Article::factory()->create(['author' => 'Bob Jones']);
        Article::factory()->create(['author' => 'Carol White']);

        $response = $this->getJson(route('v1.articles.index', ['author' => 'Alice Smith,Bob Jones']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_by_author_partial_name_returns_no_results(): void
    {
        Article::factory()->create(['author' => 'Alice Smith']);

        $response = $this->getJson(route('v1.articles.index', ['author' => 'Alice']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 0);
    }

    public function test_authors_returns_distinct_non_null_authors(): void
    {
        Article::factory()->count(2)->create(['author' => 'Alice Smith']);
        Article::factory()->create(['author' => 'Bob Jones']);
        Article::factory()->create(['author' => null]);

        $response = $this->getJson(route('v1.articles.authors'));

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    public function test_filter_by_date_range(): void
    {
        Article::factory()->create(['published_at' => '2024-01-10 12:00:00']);
        Article::factory()->create(['published_at' => '2024-06-15 12:00:00']);
        Article::factory()->create(['published_at' => '2024-12-20 12:00:00']);

        $response = $this->getJson(route('v1.articles.index', ['from' => '2024-01-01', 'to' => '2024-07-01']));

        $response->assertOk()
                 ->assertJsonPath('meta.total', 2);
    }

    public function test_per_page_param_controls_pagination(): void
    {
        Article::factory()->count(5)->create();

        $response = $this->getJson(route('v1.articles.index', ['per_page' => 2]));

        $response->assertOk()
                 ->assertJsonPath('meta.per_page', 2)
                 ->assertJsonCount(2, 'data');
    }

    public function test_invalid_per_page_returns_422(): void
    {
        $response = $this->getJson(route('v1.articles.index', ['per_page' => 999]));

        $response->assertUnprocessable();
    }

    public function test_invalid_date_returns_422(): void
    {
        $response = $this->getJson(route('v1.articles.index', ['from' => 'not-a-date']));

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/articles/{id}
    // -------------------------------------------------------------------------

    public function test_show_returns_single_article(): void
    {
        $article = Article::factory()->create(['title' => 'Specific article']);

        $response = $this->getJson(route('v1.articles.show', $article));

        $response->assertOk()
                 ->assertJsonPath('data.id', $article->id)
                 ->assertJsonPath('data.title', 'Specific article');
    }

    public function test_show_returns_404_for_missing_article(): void
    {
        $response = $this->getJson(route('v1.articles.show', 99999));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/articles/sources
    // -------------------------------------------------------------------------

    public function test_sources_returns_distinct_sources(): void
    {
        Article::factory()->count(2)->create(['source' => 'newsapi', 'source_name' => 'NewsAPI']);
        Article::factory()->create(['source' => 'guardian', 'source_name' => 'The Guardian']);

        $response = $this->getJson(route('v1.articles.sources'));

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/articles/categories
    // -------------------------------------------------------------------------

    public function test_categories_returns_distinct_non_null_categories(): void
    {
        Article::factory()->count(2)->create(['category' => 'technology']);
        Article::factory()->create(['category' => 'world']);
        Article::factory()->create(['category' => null]);

        $response = $this->getJson(route('v1.articles.categories'));

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }
}
