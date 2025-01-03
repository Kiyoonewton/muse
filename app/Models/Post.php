<?php

namespace App\Models;

use Carbon\Carbon;
use Elasticsearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'category_id',
        'status',
        'excerpt',
        'blog_id',
        'content',
        'thumbnail',
        'author_uuid',
        'visibility',
        'featured_image',
        'published_at',
        'metadata',
        'uuid',
    ];

    public static $searchable = [
        'title',
        'content',
        'excerpt',
        'slug',
    ];

    protected $appends = ['author', 'content', 'category_name', 'related_posts'];

    protected $casts = [
        'featured_image' => 'array',
        'metadata' => 'array',
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
            $object->uuid = $object->uuid ?? (string) Str::uuid();

            while (self::where('uuid', $object->uuid)->exists()) {
                $object->uuid = (string) Str::uuid();
            }
        });

        static::created(function ($post) {
            $post->indexInElasticsearch();
        });

        static::updated(function ($post) {
            $post->indexInElasticsearch();
        });

        static::deleted(function ($post) {
            try {
                Elasticsearch::delete([
                    'index' => 'posts',
                    'id' => $post->id,
                ]);
            } catch (\Exception $e) {
            }
        });
    }

    public function indexInElasticsearch()
    {
        try {
            $params = [
                'index' => 'posts',
                'id' => $this->id,
                'body' => [
                    'id' => $this->id,
                    'blog_id' => $this->blog->id,
                    'uuid' => $this->uuid,
                    'slug' => $this->slug,
                    'content' => $this->content,
                    'excerpt' => $this->excerpt,
                    'status' => (bool) $this->status,
                    'featured_image' => is_array($this->featured_image) ? $this->featured_image : null,
                    'title' => $this->title,
                    'tags' => $this->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    })->toArray(),
                    'author_uuid' => $this->author_uuid,
                    'visibility' => (bool) $this->visibility,
                    'postConfig' => (object) [],
                    'metadata' => $this->metadata,
                    'category_id' => $this->category_id,
                    'category' => $this->category ? [
                        'id' => $this->category->id,
                        'name' => $this->category->name,
                    ] : null,
                    'categories' => $this->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'uuid' => $category->uuid,
                            'slug' => $category->slug,
                        ];
                    })->toArray(),
                    'language_attr' => $this->language_attr,
                    'published_at' => $this->published_at ? Carbon::parse($this->published_at)->toAtomString() : null,
                    'created_at' => $this->created_at->toAtomString(),
                    'updated_at' => $this->updated_at->toAtomString(),
                ],
            ];
            Elasticsearch::index($params);
        } catch (\Exception $e) {
            \Log::debug($e->getMessage());
        }
    }

    public function setUuidAttribute($value)
    {
        if (! $this->exists) {
            return $this->attributes['uuid'] = $value;
        }
    }

    public function getAuthorAttribute()
    {
        return [
            'uuid' => $this->attributes['author_uuid'],
        ];
    }

    /**
     * The content is no longer top-level on the model
     * It returns the latest post revision as the content.
     */
    public function getContentAttribute()
    {
        return $this->attributes['content'] ?? '';
    }

    /**
     * Get the post excerpt. Essentially a substring of the content
     */
    public function getExcerptAttribute($value)
    {
        if ($value) {
            return $value;
        }
        $cleanSubstring = substr(strip_tags($this->content), 0, 200);

        return htmlspecialchars($cleanSubstring, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get Post Category name
     */
    public function getCategoryNameAttribute()
    {
        return $this->category ? $this->category->name : null;
    }

    /**
     * Get Related Posts
     */
    public function getRelatedPostsAttribute()
    {
        $config = $this->postConfig;
        if (! $config->related_posts_config) {
            $categoryIds = CategoryPost::where('post_id', $this->id)
                ->select('category_id')
                ->get()
                ->pluck('category_id')
                ->toArray();

            $postIds = CategoryPost::whereIn('category_id', $categoryIds)
                ->where('post_id', '!=', $this->id)
                ->limit(3)
                ->get()
                ->pluck('post_id')
                ->toArray();

            return self::whereIn('id', $postIds)->where('status', '!=', 0)->get();
        }

        $related_posts_config = json_decode($config->related_posts_config);

        return self::whereIn('uuid', $related_posts_config)->where('status', '!=', 0)->get();
    }

    /**
     * Get all of the tags for the post.
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function redirect()
    {
        return $this->hasOne(Redirect::class);
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function revision()
    {
        return $this->hasOne(Revision::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function postConfig()
    {
        return $this->hasOne(PostConfig::class);
    }

    public function revisions()
    {
        return $this->hasMany(PostRevision::class);
    }
}
