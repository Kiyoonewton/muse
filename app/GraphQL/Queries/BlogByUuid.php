<?php

namespace App\GraphQL\Queries;

use App\Models\Blog;
use Illuminate\Support\Facades\Cache;

class BlogByUuid
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        return Blog::firstWhere('uuid', $args['uuid']);
    }
}
