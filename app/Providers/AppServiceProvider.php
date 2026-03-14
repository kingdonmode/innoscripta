<?php

namespace App\Providers;

use App\Repositories\ArticleRepository;
use App\Services\News\GuardianApiFetcher;
use App\Services\News\NewsApiFetcher;
use App\Services\News\NewsAggregatorService;
use App\Services\News\NytimesFetcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NewsAggregatorService::class, function () {
            return new NewsAggregatorService(
                fetchers: [
                    new NewsApiFetcher(),
                    new GuardianApiFetcher(),
                    new NytimesFetcher(),
                ],
                repository: new ArticleRepository(),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
