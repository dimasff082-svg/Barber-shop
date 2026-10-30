<?php

namespace App\Providers;

use App\Models\Barber;
use App\Models\QueueEvent;
use App\Models\StaffAuditEntry;
use App\Observers\BarberObserver;
use App\Observers\QueueEventObserver;
use App\Observers\StaffAuditObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureQueueLimits();
        Barber::observe(BarberObserver::class);
        QueueEvent::observe(QueueEventObserver::class);
        StaffAuditEntry::observe(StaffAuditObserver::class);
        require base_path('routes/channels.php');
        $this->configureDevelopmentProcesses();
    }

    private function configureDevelopmentProcesses(): void
    {
        if (! $this->app->runningInConsole() || ! $this->app->isLocal()) {
            return;
        }

        // Same names override framework/package defaults without a second worker.
        DevCommands::artisan('queue:listen --queue=realtime,default --sleep=1 --tries=3 --timeout=30', 'queue');
        DevCommands::artisan('reverb:start', 'reverb');
        DevCommands::artisan('schedule:work', 'scheduler');
    }

    private function configureQueueLimits(): void
    {
        RateLimiter::for('queue.create', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.hash('sha256', (string) $request->ip())),
            Limit::perHour(30)->by('session:'.hash('sha256', $request->session()->getId())),
        ]);
        foreach (['access' => 10, 'guest' => 20, 'status' => 60] as $name => $count) {
            RateLimiter::for('queue.'.$name, fn (Request $request) => Limit::perMinute($count)
                ->by(hash('sha256', $name.'|'.$request->ip())));
        }
        RateLimiter::for('queue.staff', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()?->id));
        RateLimiter::for('realtime.auth', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()?->id));
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
