<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Traits\PaginatedTrait;
use App\Models\Blog;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Taggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class GetTagsByBlogUuid
{
    use PaginatedTrait;

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $limit = $args['first'];
        $page = $args['page'] ?? 1;
        $filter = $args['filter'] ?? null;
        $search = $args['search'] ?? null;
        $skip = ($page - 1) * $limit;
        $sort = isset($args['sort']) && count($args['sort']) > 0 ? $args['sort'][0] : null;

        try {
            $blog = Blog::firstWhere('uuid', $args['blog_uuid']);
            $tagIds = \DB::table('taggables')
                ->join('posts', 'taggables.taggable_id', '=', 'posts.id')
                ->where('posts.blog_id', $blog->id)
                ->where('taggables.taggable_type', 'App\Models\Post')
                ->distinct()
                ->pluck('taggables.tag_id');

            $searchFields = Tag::$searchable;

            $queryBuilder = Tag::whereIn('id', $tagIds);

            if (isset($args['all']) && $args['all']) {
                return ['data' => $queryBuilder->get(), 'paginatorInfo' => null];
            }

            return $this->getPaginatedCollection($queryBuilder, $limit, $page, $search, $sort, $filter, $searchFields);
        } catch (\Exception $e) {
            return [
                'data' => [],
                'paginatorInfo' => null,
            ];
        }
    }
}
