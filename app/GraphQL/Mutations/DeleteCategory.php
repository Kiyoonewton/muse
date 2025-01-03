<?php

namespace App\GraphQL\Mutations;

use App\Models\Category;

class DeleteCategory
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $category = Category::firstWhere('uuid', $args['uuid']);
        Category::where('parent_id', $category->id)->delete();
        $category->delete();

        return $category;
    }
}
