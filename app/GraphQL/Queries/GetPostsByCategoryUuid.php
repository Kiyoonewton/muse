<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Traits\PaginatedTrait;
use App\Models\Category;
use App\Models\Post;

final class GetPostsByCategoryUuid
{
    use PaginatedTrait;

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $limit = $args['first'];
        $page = $args['page'] ?? 1;
        $filter = $args['filter'] ?? null;
        $search = $args['search'] ?? null;
        $skip = ($page - 1) * $limit;
        $sort = isset($args['sort']) && count($args['sort']) > 0 ? $args['sort'][0] : null;

        $category = Category::firstWhere('uuid', $args['category_uuid']);
        $parentCategoryId = $category->id;
        $queryBuilder = Post::where('category_id', $parentCategoryId);

        $searchFields = Post::$searchable;

        return $this->getPaginatedCollection($queryBuilder, $limit, $page, $search, $sort, $filter, $searchFields);
    }
}
