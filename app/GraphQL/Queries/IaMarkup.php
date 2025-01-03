<?php

namespace App\GraphQL\Queries;

use App\Exceptions\GqlException;
use App\Helper\InstantArticle;
use App\Models\Blog;

class IaMarkup
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
        $blog = Blog::firstWhere('domain', $args['domain']);

        $article = $blog->posts()
        ->where('slug', $args['slug'])
        ->where('status', 1)
        ->first();

        if (! $article) {
            throw new GqlException('Article does not exist', 'Invalid slug provided');
        }

        $ia = new InstantArticle($article, $args['domain'], $args['slug']);

        return [
            'content' => $ia->getHtml(),
        ];
    }
}
