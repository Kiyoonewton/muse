<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PostRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'name',
        'status',
        'title',
        'slug',
        'content',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($object) {
            $object->uuid = (string) Str::uuid();
        });
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
