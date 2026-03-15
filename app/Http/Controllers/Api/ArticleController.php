<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleFilterRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SourceResource;
use App\Models\Article;
use App\Repositories\ArticleRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleRepository $articles) {}

    /** GET /api/v1/articles */
    public function index(ArticleFilterRequest $request): AnonymousResourceCollection
    {
        return ArticleResource::collection(
            $this->articles->paginate($request->validated())
        );
    }

    /** GET /api/v1/articles/{article} */
    public function show(Article $article): ArticleResource
    {
        return new ArticleResource($article);
    }

    /** GET /api/v1/articles/sources */
    public function sources(): AnonymousResourceCollection
    {
        return SourceResource::collection($this->articles->allSources());
    }

    /** GET /api/v1/articles/categories */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->articles->allCategories());
    }

    /** GET /api/v1/articles/authors */
    public function authors(): AnonymousResourceCollection
    {
        return AuthorResource::collection($this->articles->allAuthors());
    }
}
