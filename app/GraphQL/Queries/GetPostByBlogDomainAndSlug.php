<?php

namespace App\GraphQL\Queries;

use App\Models\Blog;

class GetPostByBlogDomainAndSlug
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $blog = Blog::firstWhere('domain', $args['domain']);
        if (! $blog) {
            return null;
        }

        return $blog->posts()
        ->where('slug', $args['slug'])
        ->where('status', 1)
        ->first();
    }
}
