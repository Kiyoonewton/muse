<?php

namespace App\GraphQL\Queries;

class GetPermissions
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        return config('permission.action');
    }
}
