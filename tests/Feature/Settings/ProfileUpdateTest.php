<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated without email verification reset', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->not->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('el usuario puede registrar su firma desde el perfil', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'firma' => UploadedFile::fake()->image('firma.png', 120, 60),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->firma)->toStartWith("firmas-usuarios/{$user->id}/")
        ->and($user->firmaUrl())->not->toBeNull();
    Storage::disk('public')->assertExists($user->firma);
});

test('al reemplazar la firma se borra el archivo anterior y se puede quitar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'firma' => UploadedFile::fake()->image('uno.png')]);
    $anterior = $user->refresh()->firma;

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'firma' => UploadedFile::fake()->image('dos.png')]);
    $nueva = $user->refresh()->firma;

    expect($nueva)->not->toBe($anterior);
    Storage::disk('public')->assertMissing($anterior);
    Storage::disk('public')->assertExists($nueva);

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'eliminar_firma' => '1']);

    expect($user->refresh()->firma)->toBeNull();
    Storage::disk('public')->assertMissing($nueva);
});

test('actualizar el perfil sin enviar firma conserva la firma registrada', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'firma' => UploadedFile::fake()->image('firma.png')]);
    $firma = $user->refresh()->firma;

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Otro nombre', 'email' => $user->email]);

    expect($user->refresh()->firma)->toBe($firma);
});

test('rechaza una firma que no es una imagen permitida', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'firma' => UploadedFile::fake()->create('firma.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('firma');
});
