<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Traits\PaginatedTrait;
use App\Models\Blog;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class GetCategoriesByBlogUuid
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

        $blog = Blog::firstWhere('uuid', $args['blog_uuid']);
        $queryBuilder = Category::where('blog_id', $blog->id);

        if ($args['is_parent']) {
            $queryBuilder = $queryBuilder->whereNull('parent_id');
        }

        $searchFields = Category::$searchable;

        if (isset($args['all']) && $args['all']) {
            $allCategories = Cache::rememberForever(sprintf('all-categories-%s', $args['blog_uuid']), function () use ($queryBuilder) {
                return ['data' => $queryBuilder->get(), 'paginatorInfo' => null];
            });

            return $allCategories;
        }

        return $this->getPaginatedCollection($queryBuilder, $limit, $page, $search, $sort, $filter, $searchFields);
    }
}
