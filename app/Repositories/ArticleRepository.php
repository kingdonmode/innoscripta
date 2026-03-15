<?php

namespace App\Repositories;

use App\Models\Article;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ArticleRepository
{
    // -------------------------------------------------------------------------
    // Reads
    // -------------------------------------------------------------------------

    /**
     * Return a paginated, filtered list of articles.
     *
     * Recognised filter keys:
     *   q, source, category, author, from, to, sort, order, per_page
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $sort    = $filters['sort']  ?? 'published_at';
        $order   = $filters['order'] ?? 'desc';

        return Article::query()
            ->search($filters['q']        ?? null)
            ->forSource($filters['source']   ?? null)
            ->forCategory($filters['category'] ?? null)
            ->forAuthor($filters['author']   ?? null)
            ->publishedFrom($filters['from']     ?? null)
            ->publishedTo($filters['to']      ?? null)
            ->orderBy($sort, $order)
            ->paginate($perPage);
    }

    public function findById(int $id): ?Article
    {
        return Article::find($id);
    }

    /** Distinct sources with article counts, ordered by name. */
    public function allSources(): Collection
    {
        return Article::query()
            ->selectRaw('source, source_name, COUNT(*) as article_count')
            ->groupBy('source', 'source_name')
            ->orderBy('source_name')
            ->get();
    }

    /** Distinct non-null categories with article counts, ordered alphabetically. */
    public function allCategories(): Collection
    {
        return Article::query()
            ->selectRaw('category, COUNT(*) as article_count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderBy('category')
            ->get();
    }

    /** Distinct non-null authors with article counts, ordered alphabetically. */
    public function allAuthors(): Collection
    {
        return Article::query()
            ->selectRaw('author, COUNT(*) as article_count')
            ->whereNotNull('author')
            ->groupBy('author')
            ->orderBy('author')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Upsert a batch of normalized article arrays.
     * Deduplicates on `url`; returns the number of rows processed.
     */
    public function upsertBatch(Collection $articles): int
    {
        if ($articles->isEmpty()) {
            return 0;
        }

        $rows = $articles
            ->filter(fn(array $a) => !empty($a['url']) && !empty($a['title']))
            ->map(fn(array $a) => $this->prepareRow($a))
            ->values()
            ->all();

        if (empty($rows)) {
            return 0;
        }

        Article::upsert(
            $rows,
            ['url'],
            [
                'source', 'external_id', 'title', 'description',
                'content', 'image_url', 'author', 'category',
                'source_name', 'published_at', 'updated_at',
            ]
        );

        return count($rows);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function prepareRow(array $article): array
    {
        $now = now()->toDateTimeString();

        return [
            'external_id'  => $article['external_id'],
            'source'       => $article['source'],
            'title'        => mb_substr((string) ($article['title'] ?? ''), 0, 255),
            'description'  => $article['description'] ?? null,
            'content'      => $article['content'] ?? null,
            'url'          => $article['url'],
            'image_url'    => $article['image_url'] ?? null,
            'author'       => $article['author'] ? mb_substr((string) $article['author'], 0, 255) : null,
            'category'     => $article['category'] ? mb_substr((string) $article['category'], 0, 255) : null,
            'source_name'  => $article['source_name'] ?? null,
            'published_at' => isset($article['published_at'])
                ? (string) $article['published_at']
                : $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }
}
