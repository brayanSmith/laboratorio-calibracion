<?php

namespace App\Providers;

use App\Modules\Ingresos\Listeners\MarcarEnvioIngresoEnviado;
use App\Modules\Recordatorios\Listeners\MarcarEnvioRecordatorioEnviado;
use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

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

        Gate::policy(Role::class, RolePolicy::class);

        // Los listeners de los módulos no están en app/Listeners, donde Laravel los descubre.
        Event::listen(MessageSent::class, MarcarEnvioRecordatorioEnviado::class);
        Event::listen(MessageSent::class, MarcarEnvioIngresoEnviado::class);

        // La consulta pública de seguimiento no pide sesión: se limitan los intentos por IP
        // para que no se puedan probar códigos al azar.
        RateLimiter::for('seguimiento', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        // Pedir el enlace del seguimiento por bahía envía un correo: pocos intentos por IP y
        // por correo, para que no se use para llenar buzones.
        RateLimiter::for('seguimiento-enlace', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(5)->by('correo:'.mb_strtolower((string) $request->input('correo'))),
        ]);
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
