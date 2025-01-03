<?php

namespace App\GraphQL\Mutations;

use App\KafkaManager;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostConfig;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreatePost
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $post = new Post();
        $postConfig = new PostConfig();

        $post->uuid = isset($args['uuid']) ? $args['uuid'] : null;
        $post->title = isset($args['title']) ? $args['title'] : null;
        $post->slug = isset($args['slug']) ? $args['slug'] : null;
        $post->author_uuid = $args['author_uuid'];
        $post->status = $args['status'];
        $post->visibility = isset($args['visibility']) ? $args['visibility'] : 1;
        $post->blog_id = Blog::where('uuid', '=', $args['blog_uuid'])->first()->id;
        $post->featured_image = isset($args['featured_image']) ? $args['featured_image'] : null;
        $post->excerpt = isset($args['excerpt']) ? $args['excerpt'] : null;
        $post->content = isset($args['content']) ? $args['content'] : null;
        $post->metadata = isset($args['metadata']) ? $args['metadata'] : null;

        $post->published_at = null;

        if ($args['status']) {
            if (isset($args['published_at']) && $args['published_at']) {
                $post->published_at = new Carbon($args['published_at']);
            } else {
                $post->published_at = Carbon::now();
            }
        }

        $post->save();

        $args['config'] = isset($args['config']) ? $args['config'] : [];
        $args['config']['post_id'] = $post->id;
        $args['config']['permalink'] = isset($args['slug']) ? $args['slug'] : null;
        $postConfig->fill($args['config']);
        $postConfig->save();

        $this->connectCategories($post, $args);
        $this->createTags($post, $args);

        // Log to Elasticsearch and emit topic
        if (isset($args['status']) && $args['status']) {
            $post->refresh();
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
                Log::channel('logstash')->info(sprintf('Muse - New post created. Emitting POST_CREATED topic for post with uuid %s', $post->uuid));
                $kafkaManager->send('POST_CREATED', $postTopicPayload);
                $kafkaManager->send('BLOG_CATEGORY_UPDATED', $categoryTopicPayload);
            }
        }

        $post->indexInElasticsearch();

        return $post;
    }

    /**
     * Creates the many-to-many association between the post
     * and its categories
     * @param App\Models\Post
     * @param array $args
     * @return void
     */
    public function connectCategories(Post $post, array $args)
    {
        if (! isset($args['category_uuids']) || count($args['category_uuids']) === 0) {
            return;
        }

        $categoryIds = Category::whereIn('uuid', $args['category_uuids'])
                        ->select('id')
                        ->get()
                        ->pluck('id')
                        ->toArray();
        $post->categories()->attach($categoryIds);
        $post->category_id = Category::firstWhere('uuid', $args['category_uuids'][0])->id;
        $post->save();
    }

    /**
     * Creates and/or links the tags that belong to the post
     * @param App\Models\Post
     * @param array $args
     * @return void
     */
    public function createTags(Post $post, array $args)
    {
        if (! isset($args['tags'])) {
            return;
        }

        $tagIds = [];
        foreach ($args['tags'] as $tagName) {
            $allcategoriesbyuuid = sprintf('all-categories-%s', $args['blog_uuid']);
            $oldCache = Cache::get($allcategoriesbyuuid);

            $tag = Tag::firstOrCreate(
                ['name' => $tagName],
            );
            array_push($tagIds, $tag->id);
        }
        $post->tags()->sync($tagIds);
    }
}
