<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait GenerateUniqueSlug
{
    protected static array $generatedSlugs = [];

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (
            static::where('slug', $slug)->exists() ||
            in_array($slug, static::$generatedSlugs, true)
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        static::$generatedSlugs[] = $slug;

        return $slug;
    }
}
