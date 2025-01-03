<?php

namespace App\GraphQL\Mutations;

use App\KafkaManager;
use App\Models\CategoryPost;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class DeletePost
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $post = Post::firstWhere('uuid', $args['uuid']);
        $category = $post->category;
        $post->delete();

        if ($category && $category->id !== null) {
            CategoryPost::where('category_id', $category->id)
            ->where('post_id', $post->id)
            ->delete();
        }

        // Log to Elasticsearch and emit topic
        $kafkaManager = new KafkaManager();
        $blog = $post->blog;
        $postTopicPayload = json_encode([
            'post' => [
                'uuid' => $post->uuid,
            ],
            'site' => [
                'uuid' => $blog->site_uuid,
            ],
        ]);
        $categoryTopicPayload = json_encode([
            'uuid' => $category ? $category->uuid : null,
        ]);

        if (strtolower(env('APP_ENV')) !== 'test') {
            Log::channel('logstash')->info(sprintf('Muse - Post deleted. Emitting POST_DELETED topic for post with uuid %s', $post->uuid));
            $kafkaManager->send('POST_DELETED', $postTopicPayload);
            $kafkaManager->send('BLOG_CATEGORY_UPDATED', $categoryTopicPayload);
        }

        return $post;
    }
}
