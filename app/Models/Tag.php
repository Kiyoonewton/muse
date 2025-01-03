<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public static $searchable = [
        'name',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($object) {
            $object->uuid = (string) Str::uuid();
        });
    }

    /**
     * Get all of the posts that are assigned this tag.
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /**
     * Get all of the categories that are assigned this tag.
     */
    public function categories()
    {
        return $this->morphedByMany(Category::class, 'taggable');
    }
}
