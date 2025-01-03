<?php

namespace App\GraphQL\Mutations;

use App\Helper\InstantArticle;
use App\Models\Blog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendIa
{
    /**
     * @var array
     */
    private $sites = [];

    /**
     * @var string
     */
    private $fbIaUrl;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var WordpressApi
     */
    private $api;

    public function __construct()
    {
        $this->fbIaUrl = env('FACEBOOK_GRAPHQL_ENDPOINT');
    }

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $this->sites[$args['domain']]['url'] = env('VT_API');
        $this->sites[$args['domain']]['fbPageId'] = env('VT_FACEBOOK_PAGE_ID');
        $this->sites[$args['domain']]['accessToken'] = env('VT_FACEBOOK_ACCESS_TOKEN');

        $blog = Blog::firstWhere('domain', $args['domain']);

        if (! $blog) {
            return null;
        }

        $article = $blog->posts()
        ->where('slug', $args['slug'])
        ->where('status', 1)
        ->first();

        $response = $this->updateIaArticle($args['domain'], $article, $args['slug']);

        return $response;
    }

    private function updateIaArticle($site, $article, $slug)
    {
        try {
            $iaHtml = $this->getArticleIaHtml($article, $site, $slug);
            $fbUpdateUrl = sprintf(
                '%s%s/instant_articles',
                $this->fbIaUrl,
                $this->sites[$site]['fbPageId'],
            );

            try {
                $response = Http::withToken($this->sites[$site]['accessToken'])->asForm()->post($fbUpdateUrl, [
                    'html_source' => $iaHtml,
                    'published' => true,
                    'development_mode' => false,
                ]);
            } catch (\Exception $e) {
                Log::emergency(sprintf('error-%s', $e->getMessage()));

                return  $e->getMessage();
            }

            if ($response->status() === 200) {
                $body = $response->json();
            }

            return [
                'id' => $body['id'],
            ];
        } catch (\Exception $e) {
            Log::info(sprintf('%s-%s', $site, $e->getMessage()));

            return  $e->getMessage();
        }
    }

    private function getArticleIaHtml($article, $site, $slug = '')
    {
        $ia = new InstantArticle($article, $site, $slug);

        return $ia->getHtml();
    }
}
