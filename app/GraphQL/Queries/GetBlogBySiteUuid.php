<?php

namespace App\GraphQL\Queries;

use App\Models\Blog;

class GetBlogBySiteUuid
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        return Blog::firstWhere('site_uuid', $args['site_uuid']);
    }
}
