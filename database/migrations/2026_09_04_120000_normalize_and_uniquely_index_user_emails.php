<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuarios.users', 'email_normalizado')) {
            Schema::table('usuarios.users', function (Blueprint $table): void {
                $table->string('email_normalizado')->nullable()->after('email');
            });
        }

        $usuarios = DB::table('usuarios.users')->select(['id', 'email'])->orderBy('id')->get();
        $vistos = [];

        foreach ($usuarios as $usuario) {
            $email = User::normalizarEmail((string) $usuario->email);

            if (isset($vistos[$email]) && $vistos[$email] !== $usuario->id) {
                throw new RuntimeException(
                    'Existen cuentas antiguas con el mismo correo al ignorar mayúsculas o espacios. '
                    .'Resuelve los duplicados antes de continuar la migración.'
                );
            }

            $vistos[$email] = $usuario->id;

            DB::table('usuarios.users')->where('id', $usuario->id)->update([
                'email' => $email,
                'email_normalizado' => $email,
            ]);
        }

        Schema::table('usuarios.users', function (Blueprint $table): void {
            $table->string('email_normalizado')->nullable(false)->change();
            $table->unique('email_normalizado', 'users_email_normalizado_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('usuarios.users', 'email_normalizado')) {
            return;
        }

        Schema::table('usuarios.users', function (Blueprint $table): void {
            $table->dropUnique('users_email_normalizado_unique');
            $table->dropColumn('email_normalizado');
        });
    }
};
