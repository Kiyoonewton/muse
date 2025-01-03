<?php

namespace App\GraphQL\Queries;

use App\Models\Blog;
use Illuminate\Support\Facades\Cache;

class GetPostByBlogUuidAndSlug
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TO-DO Reimplement Caching for this query
        $article = Cache::rememberForever(sprintf('post-%s-%s', $args['blog_uuid'], $args['slug']), function () use ($args) {
            $blog = Blog::firstWhere('uuid', $args['blog_uuid']);

            return $blog->posts()
            ->where('slug', $args['slug'])
            ->first();
        });

        return $article;
    }
}
