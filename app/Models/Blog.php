<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory;

    protected $appends = ['activeCategories', 'site'];

    protected $fillable = ['domain', 'template_uuid', 'site', 'site_uuid'];

    /**
     * @inheritdoc
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($object) {
            $object->uuid = (string) Str::uuid();
        });
    }

    /**
     * Fetches and returns categories from the blog that are currently active.
     * An active category is defined as one having at least one (1) published post.
     */
    public function getActiveCategoriesAttribute()
    {
        return $this->categories()->whereHas('posts', function (Builder $query) {
            $query->where('status', 1);
        })->get();
    }

    public function getSiteAttribute()
    {
        return [
            'uuid' => $this->attributes['site_uuid'],
        ];
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function facebookPage()
    {
        return $this->hasOne(FacebookPage::class);
    }
}
