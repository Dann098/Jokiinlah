<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Article;
use App\Models\Faq;
use App\Models\Portfolio;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Service;
use App\Models\Testimonial;
use App\Observers\OperationalActivityObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        foreach ([
            Appointment::class,
            Article::class,
            Faq::class,
            Portfolio::class,
            ProjectMilestone::class,
            Reminder::class,
            Service::class,
            Testimonial::class,
        ] as $model) {
            $model::observe(OperationalActivityObserver::class);
        }

        Date::useClass(CarbonImmutable::class);
        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised());

        RateLimiter::for('consultations', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone')) ?? '';

            if (str_starts_with($phone, '0')) {
                $phone = '62'.substr($phone, 1);
            } elseif (str_starts_with($phone, '8')) {
                $phone = '62'.$phone;
            }

            $response = fn () => response('Terlalu banyak permintaan konsultasi. Silakan tunggu dan coba kembali.', 429);

            return [
                Limit::perHour(10)->by('consultation-ip:'.hash('sha256', (string) $request->ip()))->response($response),
                Limit::perHour(5)->by('consultation-identity:'.hash('sha256', $email.'|'.$phone))->response($response),
            ];
        });

        RateLimiter::for('customer-mutations', fn (Request $request): Limit => Limit::perMinute(12)
            ->by('customer-mutation:'.hash('sha256', (string) $request->user()?->email).'|'.$request->ip())
            ->response(fn () => response('Terlalu banyak permintaan. Silakan tunggu dan coba kembali.', 429)));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands($this->app->environment('production'));
    }
}
