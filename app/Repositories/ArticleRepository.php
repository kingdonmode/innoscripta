<?php

namespace App\Repositories;

use App\Models\Article;
use Illuminate\Support\Collection;

class ArticleRepository
{
    /**
     * Upsert a batch of normalized article arrays into the database.
     * Existing records are updated; new ones are inserted.
     *
     * Returns the number of articles persisted.
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
            ['url'],                              // unique key to match on
            [                                     // columns to update on conflict
                'source_id', 'external_id', 'title', 'description',
                'content', 'image_url', 'author', 'category',
                'source_name', 'published_at', 'updated_at',
            ]
        );

        return count($rows);
    }

    private function prepareRow(array $article): array
    {
        $now = now()->toDateTimeString();

        return [
            'external_id'  => $article['external_id'],
            'source_id'    => $article['source_id'],
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
