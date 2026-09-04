<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Symfony\Component\HttpFoundation\Response;

/** Evita acceso horizontal a expedientes ajenos mediante un UUID manipulado. */
final class EnsureOwnsDepositRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $id = (string) $request->route('id');

        abort_unless($user, 403);

        if ($user->esAdministrador()) {
            return $next($request);
        }

        $owns = SolicitudDepositoEloquentModel::query()
            ->whereKey($id)
            ->where('investigador_id', $user->getKey())
            ->exists();

        // 404 evita confirmar a otro depositante que el expediente existe.
        abort_unless($owns, 404);

        return $next($request);
    }
}
