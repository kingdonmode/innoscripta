<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleFilterRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    /**
     * GET /api/articles
     *
     */
    public function index(ArticleFilterRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 15);
        $sort    = $request->validated('sort')  ?? 'published_at';
        $order   = $request->validated('order') ?? 'desc';

        $articles = Article::query()
            ->search($request->validated('q'))
            ->forSource($request->validated('source'))
            ->forCategory($request->validated('category'))
            ->forAuthor($request->validated('author'))
            ->publishedFrom($request->validated('from'))
            ->publishedTo($request->validated('to'))
            ->orderBy($sort, $order)
            ->paginate($perPage);

        return response()->json($articles);
    }

    /**
     * GET /api/articles/{article}
     */
    public function show(Article $article): JsonResponse
    {
        return response()->json(['data' => $article]);
    }

    /**
     * GET /api/articles/sources
     *
     * Returns distinct sources present in the database.
     */
    public function sources(): JsonResponse
    {
        $sources = Article::query()
            ->selectRaw('source_id, source_name, COUNT(*) as article_count')
            ->groupBy('source_id', 'source_name')
            ->orderBy('source_name')
            ->get();

        return response()->json(['data' => $sources]);
    }

    /**
     * GET /api/articles/authors
     *
     * Returns distinct non-null authors present in the database.
     */
    public function authors(): JsonResponse
    {
        $authors = Article::query()
            ->selectRaw('author, COUNT(*) as article_count')
            ->whereNotNull('author')
            ->groupBy('author')
            ->orderBy('author')
            ->get();

        return response()->json(['data' => $authors]);
    }

    /**
     * GET /api/articles/categories
     *
     * Returns distinct non-null categories present in the database.
     */
    public function categories(): JsonResponse
    {
        $categories = Article::query()
            ->selectRaw('category, COUNT(*) as article_count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return response()->json(['data' => $categories]);
    }
}
