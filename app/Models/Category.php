<?php

namespace App\Models;

use App\Traits\GenerateUniqueSlug;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use  HasFactory, GenerateUniqueSlug;

    public function products() : HasMany
    {
        return $this->hasMany(Product::class);
    }
}
