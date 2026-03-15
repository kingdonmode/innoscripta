<?php

use App\Http\Controllers\Api\ArticleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are stateless and prefixed with /api automatically by
| the framework's bootstrap/app.php configuration.
|
*/

Route::prefix('v1')->group(function () {
    Route::prefix('articles')->group(function () {
        Route::get('/', [ArticleController::class, 'index']);
        Route::get('/sources', [ArticleController::class, 'sources']);
        Route::get('/authors', [ArticleController::class, 'authors']);
        Route::get('/categories', [ArticleController::class, 'categories']);
        Route::get('/{article}', [ArticleController::class, 'show']);
    });
});
