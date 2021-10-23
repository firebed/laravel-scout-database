<?php

namespace Firebed\Scout;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class MySqlEngineServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        // Register our migrations.
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        // Register the engine.

        $this->app->make(EngineManager::class)->extend('mysql', function () {
            return new MySqlSearchEngine($this->app['db'], new UnicodeTokenizer(), new FullTextQuery());
        });
    }
}