<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Evita que la latencia SMTP revele si una cuenta existe. */
final class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
