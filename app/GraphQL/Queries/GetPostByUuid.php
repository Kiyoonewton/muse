<?php

namespace App\GraphQL\Queries;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

final class GetPostByUuid
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $post = Cache::rememberForever(sprintf('post-%s-%s', $args['blog_uuid'], $args['uuid']), function () use ($args) {
            return Post::with('category')->firstWhere('uuid', $args['uuid']);
        });

        return $post;
    }
}
