<?php

use App\Models\User;

test('un ospite non può accedere al proprio profilo', function () {
    $this->getJson('/api/user')
        ->assertUnauthorized();
});

test('un utente autenticato può visualizzare il proprio profilo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});