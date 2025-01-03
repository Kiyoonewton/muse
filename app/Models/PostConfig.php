<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'permalink',
        'is_comments_enabled',
        'is_in_sitemap',
        'is_feature_article',
        'schema',
        'social_preview_config',
        'related_posts_config',
    ];

    public function post()
    {
        return $this->hasOne(Post::class);
    }
}
