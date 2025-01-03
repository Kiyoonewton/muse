<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Traits\PaginatedTrait;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

final class PostsByBlogUuid
{
    use PaginatedTrait;

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $limit = $args['first'];
        $page = $args['page'];
        $search = $args['search'] ?? null;
        $skip = ($page - 1) * $limit;
        $sort = isset($args['sort']) && count($args['sort']) > 0 ? $args['sort'][0] : null;

        $blog = Blog::firstWhere('uuid', $args['blog_uuid']);
        $queryBuilder = Post::where('blog_id', $blog->id);
        $queryBuilder = $args['include_drafts'] ? $queryBuilder : $queryBuilder->where('status', 1);
        $searchFields = Post::$searchable;

        return $this->getPaginatedCollection($queryBuilder, $limit, $page, $search, $sort, [], $searchFields);
    }
}
