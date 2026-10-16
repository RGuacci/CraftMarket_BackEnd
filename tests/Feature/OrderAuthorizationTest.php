<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentService;
use Mockery\MockInterface;

test('un utente non può vedere un ordine altrui', function () {
    //un ordine di un altro utente, e un utente che non c'entra
    $order = Order::factory()->create();
    $other = User::factory()->create();

    // l'utente estraneo chiede quell'ordine
    $response = $this->actingAs($other)->getJson("/api/orders/{$order->id}");

    //deve essere rifiutato
    $response->assertForbidden();
});


test('il proprietario può vedere il proprio ordine', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}")
        ->assertOk();
});

test('un ospite non può vedere gli ordini', function () {
    $order = Order::factory()->create();

    $this->getJson("/api/orders/{$order->id}")
        ->assertUnauthorized();
});


test('un pagamento riuscito scala lo stock una sola volta', function () {
    $user = User::factory()->create();

    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 5.00,
    ]);

    $order = Order::factory()->for($user)->create([
        'total' => 10.00,
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 5.00,
    ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('process')
            ->once()
            ->with('card')
            ->andReturn([
                'method' => 'card',
                'status' => 'succeeded',
                'message' => 'Pagamento simulato riuscito.',
            ]);
    });

    $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'card',
        ])
        ->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 8,
    ]);
});


test('un ordine già pagato non può essere pagato una seconda volta', function () {
    $user = User::factory()->create();

    $product = Product::factory()->create([
        'stock' => 8,
        'price' => 5.00,
    ]);

    $order = Order::factory()->for($user)->create([
        'status' => 'paid',
        'total' => 10.00,
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 5.00,
    ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('process');
    });

    $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'card',
        ])
        ->assertUnprocessable();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 8,
    ]);
});


test('un ordine con pagamento fallito può essere pagato al tentativo successivo', function () {
    $user = User::factory()->create();

    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 5.00,
    ]);

    $order = Order::factory()->for($user)->create([
        'total' => 10.00,
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 5.00,
    ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('process')
            ->once()
            ->with('card')
            ->andReturn([
                'method' => 'card',
                'status' => 'failed',
                'message' => 'Pagamento non riuscito.',
            ]);

        $mock->shouldReceive('process')
            ->once()
            ->with('card')
            ->andReturn([
                'method' => 'card',
                'status' => 'succeeded',
                'message' => 'Pagamento simulato riuscito.',
            ]);
    });

    // Primo tentativo: fallisce.
    $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'card',
        ])
        ->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'failed',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 10,
    ]);

    // Secondo tentativo: riesce sullo stesso ordine.
    $this->postJson("/api/orders/{$order->id}/pay", [
        'method' => 'card',
    ])
        ->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 8,
    ]);
});

test('un pagamento con stock insufficiente non viene elaborato', function () {
    $user = User::factory()->create();

    $product = Product::factory()->create([
        'stock' => 1,
        'price' => 5.00,
    ]);

    $order = Order::factory()->for($user)->create([
        'total' => 10.00,
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 5.00,
    ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('process');
    });

    $this->actingAs($user)
        ->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stock');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 1,
    ]);
});

