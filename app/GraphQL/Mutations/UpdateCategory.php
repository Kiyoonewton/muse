<?php

namespace App\GraphQL\Mutations;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class UpdateCategory
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $category = Category::firstWhere('uuid', $args['uuid']);
        $category->fill($args);
        $category->save();
        $category->refresh();

        if ($category->wasChanged('slug')) {
            $this->updateCategoryPosts($category);
        }

        $initialSubCategories = $category->subCategories;

        if (isset($args['subCategories'])) {
            foreach ($args['subCategories'] as $subCategoryArgs) {
                if (isset($subCategoryArgs['uuid'])) {
                    $slug = str_replace(' ', '-', $subCategoryArgs['slug']);
                    Category::where('uuid', $subCategoryArgs['uuid'])
                    ->update(['name' => $subCategoryArgs['name'], 'slug' => $slug, 'parent_id' => $category->id]);
                } else {
                    $subCategoryArgs['parent_id'] = $category->id;
                    $subCategoryArgs['blog_id'] = $category->blog->id;
                    $subCategoryArgs['slug'] = str_replace(' ', '-', $subCategoryArgs['slug']);

                    Category::create($subCategoryArgs);
                }
            }
            $this->deleteSubCategories($initialSubCategories, $args['subCategories']);
        }

        $allcategoriesbyuuid = sprintf('all-categories-%s', $category->blog->uuid);
        $queryBuilder = Category::where('blog_id', $category->blog->id);

        Cache::forget($allcategoriesbyuuid);
        Cache::forever($allcategoriesbyuuid, ['data' => $queryBuilder->get(), 'paginatorInfo' => null]);

        return $category;
    }

    /**
     * Updates the slug of the posts of a category so
     * it matches/contains the slug of the
     * @param App\Models\Category $category
     * @return void
     */
    public function updateCategoryPosts(Category $category)
    {
        $posts = $category->posts;

        foreach ($posts as $post) {
            $slugSplits = explode('/', $post->slug);
            $slugSplits[1] = str_replace('/', '', $category->slug);
            $slug = implode('/', $slugSplits);
            $post->slug = str_starts_with($slug, '/') ? $slug : '/'.$slug;
            $post->save();
        }
    }

    /**
     * Deletes all dangling sub-categories (sub categories
     * that belonged initially to the category but were not
     * included in the array of updated sub categories)
     * @param App\Models\Category[] $initialSubCategories
     * @param App\Models\Category[] $subCategories
     * @return void
     */
    public function deleteSubCategories($initialSubCategories, $subCategories)
    {
        $initialUuids = $initialSubCategories->map(fn ($subCategory) => $subCategory->uuid)->toArray();
        $uuids = array_map(fn ($subCategory) => isset($subCategory['uuid']) ? $subCategory['uuid'] : 23, $subCategories);
        $deletedUuids = array_diff($initialUuids, $uuids);

        Category::whereIn('uuid', $deletedUuids)->delete();
    }
}
