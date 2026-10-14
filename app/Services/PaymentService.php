<?php

namespace App\Services;

use InvalidArgumentException;

class PaymentService
{
    public function process(string $method): array
    {
        if (!in_array($method, ['card', 'paypal'], true)) {
            throw new InvalidArgumentException(
                'Metodo di pagamento non supportato.'
            );
        }

        // in questa prima versione il pagamento riesce.
        return [
            'method' => $method,
            'status' => 'succeeded',
            'message' => 'Pagamento simulato riuscito.',
        ];
    }
}
