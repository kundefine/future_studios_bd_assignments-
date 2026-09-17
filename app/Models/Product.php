<?php

namespace App\Models;

use App\Traits\GenerateUniqueSlug;
use Illuminate\Database\Eloquent\Model;


class Product extends Model
{
    use GenerateUniqueSlug;
    protected $fillable = ["name", "slug", "price", "description", "category_id"];
}
