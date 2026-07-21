<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Application services use constructor injection.
    }

    public function boot(): void
    {
        Date::useClass(CarbonImmutable::class);
        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands($this->app->environment('production'));
    }
}
