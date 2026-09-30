<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_id','path'])]

class ProductImage extends Model
{
    public function products()
    {
        return $this->belongsTo(Product::class);
    }
}
