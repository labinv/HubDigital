<?php

use App\Models\User;

test('push configuration is private and never exposes the VAPID secret', function () {
    $user = User::factory()->create();
    config([
        'webpush.vapid.subject' => 'https://labinvepn.org',
        'webpush.vapid.public_key' => 'publica-de-prueba',
        'webpush.vapid.private_key' => 'privada-que-no-debe-salir',
    ]);

    $this->actingAs($user)
        ->getJson(route('pwa.configuration'))
        ->assertOk()
        ->assertExactJson([
            'enabled' => true,
            'publicKey' => 'publica-de-prueba',
        ])
        ->assertDontSee('privada-que-no-debe-salir');
});

test('push subscription endpoints require an authenticated and verified account', function () {
    $this->get(route('pwa.configuration'))->assertRedirect(route('login'));

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('pwa.configuration'))
        ->assertRedirect(route('verification.notice'));
});

test('a verified user can save and remove an allowed browser subscription', function () {
    $user = User::factory()->create();
    config([
        'webpush.vapid.subject' => 'https://labinvepn.org',
        'webpush.vapid.public_key' => 'publica-de-prueba',
        'webpush.vapid.private_key' => 'privada-de-prueba',
        'webpush.allowed_endpoint_hosts' => ['fcm.googleapis.com'],
    ]);

    $endpoint = 'https://fcm.googleapis.com/fcm/send/suscripcion-prueba';

    $this->actingAs($user)
        ->postJson(route('pwa.subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => str_repeat('a', 88),
                'auth' => str_repeat('b', 24),
            ],
            'contentEncoding' => 'aes128gcm',
        ])
        ->assertCreated()
        ->assertJson(['saved' => true]);

    $this->assertDatabaseHas(config('webpush.table_name'), [
        'subscribable_id' => $user->id,
        'endpoint' => $endpoint,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('pwa.subscriptions.destroy'), ['endpoint' => $endpoint])
        ->assertOk()
        ->assertJson(['deleted' => true]);

    $this->assertDatabaseMissing(config('webpush.table_name'), [
        'subscribable_id' => $user->id,
        'endpoint' => $endpoint,
    ]);
});

test('a manipulated push endpoint is rejected', function () {
    $user = User::factory()->create();
    config([
        'webpush.vapid.subject' => 'https://labinvepn.org',
        'webpush.vapid.public_key' => 'publica-de-prueba',
        'webpush.vapid.private_key' => 'privada-de-prueba',
        'webpush.allowed_endpoint_hosts' => ['fcm.googleapis.com'],
    ]);

    $this->actingAs($user)
        ->postJson(route('pwa.subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com.example.test/robo',
            'keys' => [
                'p256dh' => str_repeat('a', 88),
                'auth' => str_repeat('b', 24),
            ],
            'contentEncoding' => 'aes128gcm',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});
