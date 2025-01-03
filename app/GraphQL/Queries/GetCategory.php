<?php

namespace App\GraphQL\Queries;

use App\Models\Category as CategoryModel;

class GetCategory
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        return CategoryModel::firstWhere('uuid', $args['uuid']);
    }
}
