<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id','path'])]

class ProductImage extends Model
{
    public function products(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
