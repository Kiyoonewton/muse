<?php

namespace App\GraphQL\Mutations;

use App\Models\PostRevision;

final class DeletePostRevision
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $postRevision = PostRevision::firstWhere('uuid', $args['uuid']);
        $postRevision->delete();

        return $postRevision;
    }
}
