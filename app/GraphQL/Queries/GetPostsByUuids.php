<?php

namespace App\GraphQL\Queries;

use App\Models\Post;

final class GetPostsByUuids
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        return Post::whereIn('uuid', $args['uuids'])
        ->where('status', 1)
        ->get();
    }
}
