<?php

namespace App\GraphQL\Queries;

use App\Models\Post;

final class GetPostsByAuthorUuid
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $limit = $args['first'];
        $skip = ($args['page'] - 1) * $limit;

        $posts = Post::where('author_uuid', $args['author_uuid'])
        ->paginate($limit, ['*'], 'page', $args['page']);

        return [
            'data' => $posts,
            'paginatorInfo' => $this->getPagination($posts),
        ];
    }

    public function getPagination($posts)
    {
        return [
            'count' => $posts->count(),
            'currentPage' => $posts->currentPage(),
            'hasMorePages' => $posts->lastPage() > $posts->currentPage() ? true : false,
            'lastPage' => $posts->lastPage(),
            'perPage' => $posts->count(),
            'total' => $posts->total(),
        ];
    }
}
