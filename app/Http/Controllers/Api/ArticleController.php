<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleFilterRequest;
use App\Models\Article;
use App\Repositories\ArticleRepository;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleRepository $articles) {}

    /** GET /api/articles */
    public function index(ArticleFilterRequest $request): JsonResponse
    {
        return response()->json(
            $this->articles->paginate($request->validated())
        );
    }

    /** GET /api/articles/{article} */
    public function show(Article $article): JsonResponse
    {
        return response()->json(['data' => $article]);
    }

    /** GET /api/articles/sources */
    public function sources(): JsonResponse
    {
        return response()->json(['data' => $this->articles->allSources()]);
    }

    /** GET /api/articles/categories */
    public function categories(): JsonResponse
    {
        return response()->json(['data' => $this->articles->allCategories()]);
    }

    /** GET /api/articles/authors */
    public function authors(): JsonResponse
    {
        return response()->json(['data' => $this->articles->allAuthors()]);
    }
}
