<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemMailConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SystemMailConfigurationService
{
    public function stored(): ?SystemMailConfiguration
    {
        if (! Schema::hasTable('usuarios.configuracion_correo')) {
            return null;
        }

        return SystemMailConfiguration::query()->find(1);
    }

    public function applyStored(): void
    {
        $configuration = $this->stored();
        if ($configuration !== null) {
            $this->apply($configuration);
        }
    }

    /** Importación inicial: después la base es la única fuente administrable. */
    public function importEnvironmentIfMissing(): void
    {
        // El candidato OCI puede arrancar antes de aplicar esta migracion.
        // En ese intervalo SMTP debe seguir usando el entorno sin intentar
        // insertar en una tabla que aun no existe.
        if (app()->environment('testing')
            || ! Schema::hasTable('usuarios.configuracion_correo')
            || $this->stored() !== null) {
            return;
        }

        $password = (string) config('mail.mailers.smtp.password');
        $username = (string) config('mail.mailers.smtp.username');
        $fromAddress = (string) config('mail.from.address');

        if ($password === '' || $username === '' || $fromAddress === '') {
            return;
        }

        SystemMailConfiguration::query()->firstOrCreate(
            ['id' => 1],
            [
                'host' => (string) config('mail.mailers.smtp.host', 'smtp.zoho.com'),
                'port' => (int) config('mail.mailers.smtp.port', 587),
                'username' => $username,
                'password' => $password,
                'from_address' => $fromAddress,
                'from_name' => (string) config('mail.from.name', 'HubDigital'),
                'updated_by' => null,
            ],
        );
    }

    /**
     * @param  array{host:string,port:int,username:string,from_address:string,from_name:string}  $data
     */
    public function save(array $data, #[\SensitiveParameter] string $password, User $actor): SystemMailConfiguration
    {
        $existing = $this->stored();
        $effectivePassword = $password !== ''
            ? $password
            : (string) ($existing?->password ?? config('mail.mailers.smtp.password'));

        if ($effectivePassword === '') {
            throw new \DomainException('Ingresa la contraseña de aplicación SMTP.');
        }

        $configuration = SystemMailConfiguration::query()->updateOrCreate(
            ['id' => 1],
            [
                ...$data,
                'password' => $effectivePassword,
                'updated_by' => $actor->getKey(),
            ],
        );

        $this->apply($configuration);

        return $configuration;
    }

    public function apply(SystemMailConfiguration $configuration): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $configuration->host,
            'mail.mailers.smtp.port' => $configuration->port,
            'mail.mailers.smtp.username' => $configuration->username,
            'mail.mailers.smtp.password' => $configuration->password,
            'mail.from.address' => $configuration->from_address,
            'mail.from.name' => $configuration->from_name,
        ]);
    }
}
