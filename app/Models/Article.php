<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;
    protected $fillable = [
        'external_id',
        'source',
        'title',
        'description',
        'content',
        'url',
        'image_url',
        'author',
        'category',
        'source_name',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('content', 'like', "%{$term}%");
        });
    }

    public function scopeForSource(Builder $query, ?string $source): Builder
    {
        if (blank($source)) {
            return $query;
        }

        $ids = array_filter(array_map('trim', explode(',', $source)));

        return empty($ids) ? $query : $query->whereIn('source', $ids);
    }

    public function scopeForCategory(Builder $query, ?string $category): Builder
    {
        if (blank($category)) {
            return $query;
        }

        $cats = array_filter(array_map('trim', explode(',', $category)));

        return empty($cats) ? $query : $query->whereIn('category', $cats);
    }

    public function scopeForAuthor(Builder $query, ?string $author): Builder
    {
        if (blank($author)) {
            return $query;
        }

        $authors = array_filter(array_map('trim', explode(',', $author)));

        return empty($authors) ? $query : $query->whereIn('author', $authors);
    }

    public function scopePublishedFrom(Builder $query, ?string $from): Builder
    {
        if (blank($from)) {
            return $query;
        }

        return $query->where('published_at', '>=', $from);
    }

    public function scopePublishedTo(Builder $query, ?string $to): Builder
    {
        if (blank($to)) {
            return $query;
        }

        return $query->where('published_at', '<=', $to);
    }
}
