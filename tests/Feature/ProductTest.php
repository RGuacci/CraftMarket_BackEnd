<?php

use App\Models\Product;
use App\Models\User;

test('il catalogo è pubblico e non espone i dati del venditore', function () {
    Product::factory()->count(3)->create();

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissingPath('data.0.user.email');
});

test('un utente normale non può creare prodotti', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', [])
        ->assertForbidden();
});

$payload = [
    'name' => 'Prodotto modificato',
    'description' => 'Descrizione',
    'price' => 10,
    'stock' => 1,
];

test('un seller non può modificare il prodotto altrui', function () use ($payload) {
    $seller = User::factory()->seller()->create();
    $product = Product::factory()->create(); // di un altro venditore

    $this->actingAs($seller)
        ->putJson("/api/products/{$product->slug}", $payload)
        ->assertForbidden();

    expect($product->fresh()->name)->not->toBe('Prodotto modificato');
});
