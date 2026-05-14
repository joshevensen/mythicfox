<?php

namespace App\Providers;

use App\Services\Backup\PgDumpRunner;
use App\Services\Backup\SubprocessRunner;
use App\Services\SellerStats\BrowsershotStorefrontFetcher;
use App\Services\SellerStats\StorefrontFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StorefrontFetcher::class, BrowsershotStorefrontFetcher::class);
        $this->app->bind(SubprocessRunner::class, PgDumpRunner::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
    }

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
