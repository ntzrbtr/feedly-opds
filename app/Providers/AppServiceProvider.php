<?php

namespace App\Providers;

use App\Services\Feedly\FeedlyClient;
use App\Services\Instapaper\InstaparserClient;
use App\Services\Opds\OpdsDocumentBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerFeedlyClient();
        $this->registerInstaparserClient();
        $this->registerOpdsDocumentBuilder();
    }

    private function registerFeedlyClient(): void
    {
        $this->app->singleton(FeedlyClient::class, function ($app): FeedlyClient {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('feedly');

            return new FeedlyClient(
                baseUrl: (string) $config['base_url'],
                developerToken: $config['developer_token'] ? (string) $config['developer_token'] : null,
                refreshToken: $config['refresh_token'] ? (string) $config['refresh_token'] : null,
                clientId: $config['client_id'] ? (string) $config['client_id'] : null,
                clientSecret: $config['client_secret'] ? (string) $config['client_secret'] : null,
            );
        });
    }

    private function registerInstaparserClient(): void
    {
        $this->app->singleton(InstaparserClient::class, function ($app): InstaparserClient {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('instaparser');

            return new InstaparserClient(
                baseUrl: (string) $config['base_url'],
                apiKey: $config['api_key'] ? (string) $config['api_key'] : null,
                cacheTtl: (int) ($config['cache']['article_ttl'] ?? 86400),
            );
        });
    }

    private function registerOpdsDocumentBuilder(): void
    {
        $this->app->singleton(OpdsDocumentBuilder::class, function ($app): OpdsDocumentBuilder {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('opds');

            return new OpdsDocumentBuilder(
                title: (string) ($config['title'] ?? 'Feedly Read Later'),
                author: (string) ($config['author'] ?? 'Feedly OPDS Server'),
                authorUri: (string) ($config['author_uri'] ?? ''),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
