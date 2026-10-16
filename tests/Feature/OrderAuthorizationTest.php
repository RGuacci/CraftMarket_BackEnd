<?php

use App\Models\Order;
use App\Models\User;

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