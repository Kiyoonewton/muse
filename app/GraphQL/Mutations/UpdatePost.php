<?php

namespace App\GraphQL\Mutations;

use App\KafkaManager;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostConfig;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdatePost
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $post = Post::firstWhere('uuid', $args['uuid']);
        $revisionContent = $post->content;
        $postConfig = $post->postConfig ?? PostConfig::create(['post_id' => $post->id]);
        $config = isset($args['config']) ? $args['config'] : [];

        if (isset($args['status'])) {
            if (! $post->published_at && $args['status']) {
                $post->published_at = Carbon::now();
            }
        }

        $post->fill(array_intersect_key($args, array_flip($post->getFillable())));
        $postConfig->fill(array_intersect_key($config, array_flip($postConfig->getFillable())));

        $post->save();
        $postConfig->save();

        if (isset($args['content'])) {
            $post->revisions()->create([
                'name' => isset($args['version']) ? $args['version'] : null,
                'status' => $post->status,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $revisionContent,
            ]);
        }

        $this->updateCategories($post, $args);
        $this->updateTags($post, $args);

        // Log to Elasticsearch and emit topic
        $kafkaManager = new KafkaManager();
        $category = $post->category;
        $blog = $post->blog;
        $postTopicPayload = json_encode([
            'post' => [
                'uuid' => $post->uuid,
                'title' => $post->title,
                'slug' => $post->slug,
                'metadata' => json_encode(isset($args['config']) ? $args['config'] : []),
                'status' => $post->status,
            ],
            'site' => [
                'uuid' => $blog->site_uuid,
            ],
        ]);
        $categoryTopicPayload = json_encode([
            'uuid' => $category ? $category->uuid : null,
        ]);

        if (strtolower(env('APP_ENV')) !== 'test') {
            Log::channel('logstash')->info(sprintf('Muse - Post updated. Emitting POST_UPDATED topic for post with uuid %s', $post->uuid));
            $kafkaManager->send('POST_UPDATED', $postTopicPayload);
            $kafkaManager->send('BLOG_CATEGORY_UPDATED', $categoryTopicPayload);
        }

        // clear caches
        $postbybloguuidslugkey = sprintf('post-%s-%s', $post->blog->uuid, $post['slug']);
        $postbyuuidkey = sprintf('post-%s-%s', $post->blog->uuid, $args['uuid']);

        Cache::forget($postbybloguuidslugkey);
        Cache::forget($postbyuuidkey);

        Cache::forever($postbybloguuidslugkey, $post);
        Cache::forever($postbyuuidkey, $post);

        $post->indexInElasticsearch();

        return $post;
    }

    /**
     * Updates the many-to-many association between the post
     * and its categories.
     * @param App\Models\Post
     * @param array $args
     * @return void
     */
    public function updateCategories(Post $post, array $args)
    {
        if (! isset($args['category_uuids']) || count($args['category_uuids']) === 0) {
            return;
        }

        $categoryIds = Category::whereIn('uuid', $args['category_uuids'])
                        ->select('id')
                        ->get()
                        ->pluck('id')
                        ->toArray();
        $post->categories()->sync($categoryIds);
        $post->category_id = Category::firstWhere('uuid', $args['category_uuids'][0])->id;
        $post->save();
    }

    /**
     * Updates the many-to-many association between the post
     * and its tags
     * @param App\Models\Post
     * @param array $args
     * @return void
     */
    public function updateTags(Post $post, array $args)
    {
        if (! isset($args['tags'])) {
            return;
        }

        $tagIds = [];
        foreach ($args['tags'] as $tagName) {
            $tag = Tag::firstOrCreate(
                ['name' => $tagName],
            );
            array_push($tagIds, $tag->id);
        }
        $post->tags()->sync($tagIds);
    }
}
