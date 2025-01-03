<?php

namespace App\GraphQL\Mutations;

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CreateCategory
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $blog = Blog::firstWhere('uuid', $args['blog_uuid']);
        $args['slug'] = str_replace(' ', '-', $args['slug']);

        if (isset($args['parent_uuid'])) {
            $parentCategory = Category::firstWhere('uuid', $args['parent_uuid']);
            $args['parent_id'] = $parentCategory->id;
            unset($args['parent_uuid']);
        }

        $category = $blog->categories()->create($args);

        if (isset($args['subCategories'])) {
            foreach ($args['subCategories'] as $subCategoryArgs) {
                $subCategoryArgs['parent_id'] = $category->id;
                $subCategoryArgs['blog_id'] = $blog->id;
                $subCategoryArgs['slug'] = str_replace(' ', '-', $subCategoryArgs['slug']);

                Category::create($subCategoryArgs);
            }
        }

        $allcategoriesbyuuid = sprintf('all-categories-%s', $args['blog_uuid']);
        $queryBuilder = Category::where('blog_id', $blog->id);

        Cache::forget($allcategoriesbyuuid);
        Cache::forever($allcategoriesbyuuid, ['data' => $queryBuilder->get(), 'paginatorInfo' => null]);

        return $category;
    }
}
