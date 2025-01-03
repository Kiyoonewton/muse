<?php

namespace App\GraphQL\Mutations;

use App\Models\Blog;

final class DeleteBlog
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $blog = Blog::firstWhere('uuid', $args['uuid']);
        $blog->delete();

        return $blog;
    }
}
