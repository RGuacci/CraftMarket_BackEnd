<?php

namespace App\Policies;

use App\Models\SellerRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SellerRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, SellerRequest $sellerRequest): bool
    {
        return $user->role === 'admin'
            || $sellerRequest->user_id === $user->id;
    }

    public function update(User $user, SellerRequest $sellerRequest): bool
    {
        return $user->role === 'admin'
            && $sellerRequest->status === 'pending';
    }

    public function create(User $user): bool
    {
        return $user->role === 'user';
    }
}
