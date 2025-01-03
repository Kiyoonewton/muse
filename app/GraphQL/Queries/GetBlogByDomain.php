<?php

namespace App\GraphQL\Queries;

use App\Models\Blog;
use Illuminate\Support\Facades\Cache;

class GetBlogByDomain
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $postsLimit = $args['postsCount'];
        $postsSkip = ($args['postsPage'] - 1) * $postsLimit;
        $blog = Blog::firstWhere('domain', $args['domain']);
        $blog->postsPaginatorInfo = $this->getPostsPagination($blog, $args['activePosts'], $args['postsPage'], $postsLimit);

        if ($args['activePosts']) {
            $blog->posts = $blog->posts()
            ->orderBy('created_at', 'desc')
            ->where('status', 1)
            ->skip($postsSkip)
            ->limit($postsLimit)
            ->get();

            return $blog;
        }

        $blog->posts = $blog->posts()
        ->withoutGlobalSCope('showPublished')
        ->orderBy('created_at', 'desc')
        ->skip($postsSkip)
        ->limit($postsLimit)
        ->get();

        return $blog;
    }

    /**
     * Determine post pagination information for the query
     * @param Blog blog
     * @param bool $activePosts
     * @param int $postsPage
     * @param int $postsCount
     * @return array
     */
    public function getPostsPagination(Blog $blog, Bool $activePosts, Int $postsPage, Int $postsCount)
    {
        if ($activePosts) {
            $totalPosts = $blog->loadCount(['posts' => function ($query) {
                $query->where('status', 1);
            }])->posts_count;
        } else {
            $totalPosts = $blog->loadCount('posts')->posts_count;
        }

        $lastPage = ceil($totalPosts / $postsCount);

        return [
            'currentPage' => $postsPage,
            'lastPage' => $lastPage,
        ];
    }
}
