<?php

namespace App\GraphQL\Queries;

use App\Models\Post;
use App\Models\PostRevision;

class GetPostRevisions
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $post = Post::firstWhere('uuid', $args['post_uuid']);

        return $post->revisions()
        ->orderBy('created_at', 'DESC')
        ->get();
    }
}
