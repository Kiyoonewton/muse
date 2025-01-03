<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Exceptions\GqlException;
use App\Models\Blog;
use Exception;

final class GetBlog
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): object | null
    {
        // TODO implement the resolver
        $filter = isset($args['filter']) ? $args['filter'] : null;

        $blogQueryBuilder = Blog::where('uuid', $args['uuid']);

        if ($filter) {
            $blogQueryBuilder->where($filter['column'], $filter['operator'], $filter['value']);
        }

        return $blogQueryBuilder->first();
    }
}
