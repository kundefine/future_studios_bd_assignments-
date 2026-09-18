<?php

namespace App\Models;

use App\Traits\GenerateUniqueSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use GenerateUniqueSlug, HasFactory;

    protected $fillable = ['name', 'slug', 'price', 'description', 'category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
