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

Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('articles')->name('articles.')->group(function () {
        Route::get('/', [ArticleController::class, 'index'])->name('index');
        Route::get('/sources', [ArticleController::class, 'sources'])->name('sources');
        Route::get('/authors', [ArticleController::class, 'authors'])->name('authors');
        Route::get('/categories', [ArticleController::class, 'categories'])->name('categories');
        Route::get('/{article}', [ArticleController::class, 'show'])->name('show');
    });
});
