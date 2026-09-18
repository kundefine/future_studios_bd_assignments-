<?php

namespace App\Models;

use App\Traits\GenerateUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Product extends Model
{
    use GenerateUniqueSlug;
    protected $fillable = ["name", "slug", "price", "description", "category_id"];

    public function category() : BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
