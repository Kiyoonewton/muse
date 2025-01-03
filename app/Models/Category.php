<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'slug', 'description', 'blog_id'];

    protected $appends = ['subCategories', 'parent', 'post_count'];

    protected $filterable = [
        'name',
        'slug',
        'uuid',
        'description',
        'created_at',
        'updated_at',
    ];

    public static $searchable = [
        'name',
    ];

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

    public function getPostCountAttribute()
    {
        if ($this->parent_id) {
            $this->loadCount('posts');

            return $this->posts_count;
        }

        $categoryIds = self::where('parent_id', $this->id)
        ->select('id')
        ->get()
        ->pluck('id')
        ->toArray();

        array_push($categoryIds, $this->id);

        return DB::table('category_post')
        ->whereIn('category_id', $categoryIds)
        ->count();
    }

    public function getParentAttribute()
    {
        if (! $this->parent_id) {
            return null;
        }

        return self::find($this->parent_id);
    }

    public function getSubCategoriesAttribute()
    {
        return self::where('parent_id', $this->id)->get();
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class);
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    /**
     * Get all of the tags for the category.
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }
}
