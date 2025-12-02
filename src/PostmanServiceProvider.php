<?php

namespace dogukantokgoz\Route2Postman;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use dogukantokgoz\Route2Postman\Collections\AuthenticationBuilder;
use dogukantokgoz\Route2Postman\Collections\Builder;
use dogukantokgoz\Route2Postman\Collections\CollectionExporter;
use dogukantokgoz\Route2Postman\Collections\HeaderBuilder;
use dogukantokgoz\Route2Postman\Collections\RequestBodyGenerator;
use dogukantokgoz\Route2Postman\Collections\RequestNameGenerator;
use dogukantokgoz\Route2Postman\Collections\RouteGrouper;
use dogukantokgoz\Route2Postman\Collections\UrlBuilder;
use dogukantokgoz\Route2Postman\Commands\GeneratePostmanDocs;
use dogukantokgoz\Route2Postman\Contracts\RouteAnalyzerInterface;
use dogukantokgoz\Route2Postman\Services\RouteAnalyzer;

class PostmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman.php', 'postman');

        $this->app->singleton(RouteAnalyzerInterface::class, function ($app) {
            return new RouteAnalyzer(
                $app->make(Router::class),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(AuthenticationBuilder::class, function ($app) {
            return new AuthenticationBuilder();
        });

        $this->app->singleton(HeaderBuilder::class, function ($app) {
            return new HeaderBuilder();
        });

        $this->app->singleton(UrlBuilder::class, function ($app) {
            return new UrlBuilder();
        });

        $this->app->singleton(RequestNameGenerator::class, function ($app) {
            return new RequestNameGenerator(
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(RequestBodyGenerator::class, function ($app) {
            return new RequestBodyGenerator();
        });

        $this->app->singleton(RouteGrouper::class, function ($app) {
            return new RouteGrouper(
                $app->make(Config::class)->get('postman.collection.grouping_strategy', 'prefix'),
                $app->make(Config::class)->get('postman', []),
                $app->make(RequestNameGenerator::class),
                $app->make(RequestBodyGenerator::class),
                $app->make(HeaderBuilder::class),
                $app->make(AuthenticationBuilder::class),
                $app->make(UrlBuilder::class)
            );
        });

        $this->app->singleton(Builder::class, function ($app) {
            return new Builder(
                $app->make(RouteGrouper::class),
                $app->make(AuthenticationBuilder::class),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(CollectionExporter::class, function ($app) {
            return new CollectionExporter(
                $app->make(Builder::class),
                $app->make(Config::class)->get('postman', [])
            );
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GeneratePostmanDocs::class]);

            $this->publishes([
                __DIR__ . '/../config/postman.php' => config_path('postman.php'),
            ], 'postman-config');
        }
    }
}
