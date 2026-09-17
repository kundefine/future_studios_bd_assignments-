<?php

namespace App\Models;

use App\Traits\GenerateUniqueSlug;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use  HasFactory, GenerateUniqueSlug;
}
