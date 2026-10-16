
<?php

use App\Models\User;
use App\Models\SellerRequest;

test('un utente autenticato può inviare una richiesta seller', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/seller-requests', [
        'description' => 'Creo prodotti artigianali.',
        'category' => 'Artigianato',
        'motivation' => 'Vorrei vendere i miei prodotti su Satisfy.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Richiesta inviata correttamente.');

    $this->assertDatabaseHas('seller_requests', [
        'user_id' => $user->id,
        'category' => 'Artigianato',
        'status' => 'pending',
    ]);
});

test('un utente non autenticato non può inviare una richiesta seller', function () {
    $response = $this->postJson('/api/seller-requests', [
        'description' => 'Creo prodotti artigianali.',
        'category' => 'Artigianato',
        'motivation' => 'Vorrei vendere i miei prodotti su Satisfy.',
    ]);

    $response->assertUnauthorized();
});

test('un utente non può inviare una seconda richiesta seller pendente', function () {
    $user = User::factory()->create();

    SellerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->postJson('/api/seller-requests', [
        'description' => 'Creo altri prodotti.',
        'category' => 'Artigianato',
        'motivation' => 'Vorrei presentare una nuova candidatura.',
    ]);

    $response->assertStatus(409);

    $this->assertDatabaseCount('seller_requests', 1);
});

test('un seller non può inviare una richiesta seller', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
    ]);

    $response = $this->actingAs($seller)->postJson('/api/seller-requests', [
        'description' => 'Creo prodotti artigianali.',
        'category' => 'Artigianato',
        'motivation' => 'Vorrei vendere i miei prodotti su Satisfy.',
    ]);

    $response->assertForbidden();
});

test('una richiesta seller richiede tutti i campi obbligatori', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/seller-requests', [
        'description' => '',
        'category' => '',
        'motivation' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'description',
            'category',
            'motivation',
        ]);
});


it('un admin può approvare una richiesta seller', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $sellerRequest = SellerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/seller-requests/{$sellerRequest->id}/approve")
        ->assertOk()
        ->assertJson([
            'message' => 'Richiesta approvata.',
        ]);

    $this->assertDatabaseHas('seller_requests', [
        'id' => $sellerRequest->id,
        'status' => 'approved',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'seller',
    ]);
});

it('un admin può rifiutare una richiesta seller', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $sellerRequest = SellerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/seller-requests/{$sellerRequest->id}/reject")
        ->assertOk()
        ->assertJson([
            'message' => 'Richiesta rifiutata.',
        ]);

    $this->assertDatabaseHas('seller_requests', [
        'id' => $sellerRequest->id,
        'status' => 'rejected',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'user',
    ]);
});

it('un utente normale non può approvare una richiesta seller', function () {
    $user = User::factory()->create(['role' => 'user']);
    $applicant = User::factory()->create(['role' => 'user']);

    $sellerRequest = SellerRequest::factory()->create([
        'user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson("/api/seller-requests/{$sellerRequest->id}/approve")
        ->assertForbidden();

    $this->assertDatabaseHas('seller_requests', [
        'id' => $sellerRequest->id,
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $applicant->id,
        'role' => 'user',
    ]);
});

it('non si può approvare una richiesta già elaborata', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $sellerRequest = SellerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => 'rejected',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/seller-requests/{$sellerRequest->id}/approve")
        ->assertForbidden();

    $this->assertDatabaseHas('seller_requests', [
        'id' => $sellerRequest->id,
        'status' => 'rejected',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'user',
    ]);
});


it('un utente può consultare la propria richiesta seller', function () {
    $user = User::factory()->create(['role' => 'user']);

    $sellerRequest = SellerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->getJson('/api/seller-requests/me')
        ->assertOk()
        ->assertJsonPath('data.id', $sellerRequest->id)
        ->assertJsonPath('data.status', 'pending');
});

it('un utente senza richieste riceve data null', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->getJson('/api/seller-requests/me')
        ->assertOk()
        ->assertJsonPath('data', null);
});
