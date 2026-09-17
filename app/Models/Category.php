<?php

namespace App\Models;

use App\Traits\GenerateUniqueSlug;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use GenerateUniqueSlug;
}
