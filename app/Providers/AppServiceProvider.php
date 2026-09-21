<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureTranslations();
        $this->preventExternalNotificationsDuringValidation();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureTranslations(): void
    {
        $this->app['translator']->addLines([
            'validation.uploaded' => 'El archivo :attribute no pudo cargarse. Verifica que sea un PDF de máximo 10 MB.',
            'validation.attributes.pdfFirmado' => 'acta firmada',
            'validation.attributes.documentoIdentidad' => 'documento de identidad',
            'validation.attributes.documentoIdentidadSolo' => 'documento de identidad',
        ], 'en');
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

    /**
     * La restauracion se valida sobre datos coordinados que pueden conservar
     * destinatarios reales. MAIL_MAILER=log por si solo no cubre Web Push u
     * otros canales de Laravel Notification; en modo de validacion se permite
     * solamente la campana interna de base de datos.
     */
    protected function preventExternalNotificationsDuringValidation(): void
    {
        if (! config('hubdigital.validation_mode')) {
            return;
        }

        Event::listen(NotificationSending::class, static function (NotificationSending $event): ?bool {
            return $event->channel === 'database' ? null : false;
        });
    }
}
