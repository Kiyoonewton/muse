<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait CacheTrait
{
    public function forgetBlogCache($blog)
    {
        Cache::forget(sprintf('blog-by-domain-%s', $blog->domain));
        Cache::forget(sprintf('blog-by-site-uuid-%s', $blog->site_uuid));
        Cache::forget(sprintf('blog-by-uuid-%s', $blog->uuid));
    }

    public function forgetCategoryCache($category)
    {
        Cache::forget(sprintf('category-by-uuid-%s', $category->uuid));
        Cache::forget(sprintf('categories-by-blog-uuid-%s', $category->blog->uuid));
    }

    public function forgetPostCache($post)
    {
        $category = $post->category;
        $category_id = $category->id;
        $category_uuid = $category->uuid;

        $blog = $post->blog;
        $domain = $blog->domain;

        Cache::forget(sprintf('posts-by-category-by-id-%s', $category_id));
        Cache::forget(sprintf('posts-by-category-by-id-%s', $category_uuid));
        Cache::forget(sprintf('post-by-domain-%s-and-slug-%s', $domain, $post->slug));
        Cache::forget(sprintf('post-by-uuid-%s', $post->uuid));
        Cache::forget(sprintf('post-by-author-uuid-%s', $post->author_uuid));
        Cache::forget('all-posts');
    }
}
