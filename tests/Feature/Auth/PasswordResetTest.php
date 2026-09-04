<?php

use App\Models\User;
use App\Notifications\Auth\QueuedResetPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password reset email is queued to reduce account enumeration timing', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);

        return true;
    });
});

test('password recovery does not reveal whether an account exists', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'existente@example.com']);

    $existing = $this->post(route('password.email'), ['email' => $user->email]);
    $existing->assertSessionHas('status');
    $genericMessage = session('status');

    $missing = $this->post(route('password.email'), ['email' => 'no-existe@example.com']);
    $missing->assertSessionHas('status', $genericMessage);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password recovery recognizes a canonicalized email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'recuperar@example.com']);

    $this->post(route('password.email'), ['email' => ' RECUPERAR@EXAMPLE.COM '])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->createToken('antes-de-recuperar-clave');
    DB::table('sessions')->insert([
        'id' => 'sesion-anterior-reset',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        expect($user->tokens()->count())->toBe(0);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-anterior-reset']);

        return true;
    });
});
