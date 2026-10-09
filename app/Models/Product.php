<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'price', 'stock', 'user_id'])]

class Product extends Model

 {
   use SoftDeletes;   
 
   public function getRouteKeyName(): string
    {
        return 'slug';
    }
 

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function carts()
    {
        return $this->belongsToMany(Cart::class)->withPivot('quantity');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
