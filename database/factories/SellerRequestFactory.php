<?php

namespace Database\Factories;

use App\Models\SellerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerRequest>
 */
class SellerRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => 'Creo prodotti artigianali.',
            'category' => 'Artigianato',
            'motivation' => 'Vorrei vendere su Satisfy.',
            'status' => 'pending',
        ];
    }
}
