<?php

namespace App\Console\Commands;

use App\Models\Post;
use Carbon\Carbon;
use Elasticsearch;
use Elasticsearch\Client;
use Illuminate\Console\Command;

class IndexPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = 0;

        if (Elasticsearch::indices()->exists(['index' => 'posts'])) {
            Elasticsearch::indices()->delete(['index' => 'posts']);
        }

        $params = [
            'index' => 'posts',
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'tokenizer' => [
                            'edge_ngram_tokenizer' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 1,
                                'max_gram' => 10,
                                'token_chars' => ['letter', 'digit'],
                            ],
                        ],
                        'analyzer' => [
                            'edge_ngram_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'edge_ngram_tokenizer',
                                'filter' => ['lowercase'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'uuid' => ['type' => 'keyword'],
                        'slug' => [
                            'type' => 'text',
                            'analyzer' => 'edge_ngram_analyzer',
                            'fields' => [
                                'raw' => ['type' => 'keyword'],
                            ],
                        ],
                        'content' => ['type' => 'text', 'analyzer' => 'edge_ngram_analyzer'],
                        'excerpt' => ['type' => 'text', 'analyzer' => 'edge_ngram_analyzer'],
                        'status' => ['type' => 'boolean'],
                        'featured_image' => ['type' => 'object'],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'edge_ngram_analyzer',
                            'fields' => [
                                'raw' => ['type' => 'keyword'],
                            ],
                        ],
                        'tags' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'keyword'],
                                'name' => [
                                    'type' => 'text',
                                    'analyzer' => 'edge_ngram_analyzer',
                                    'fields' => [
                                        'raw' => ['type' => 'keyword'],
                                    ],
                                ],
                            ],
                        ],
                        'author_uuid' => ['type' => 'keyword'],
                        'visibility' => ['type' => 'boolean'],
                        'postConfig' => ['type' => 'object'],
                        'metadata' => ['type' => 'object'],
                        'category' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'keyword'],
                                'name' => [
                                    'type' => 'text',
                                    'analyzer' => 'edge_ngram_analyzer',
                                    'fields' => [
                                        'raw' => ['type' => 'keyword'],
                                    ],
                                ],
                            ],
                        ],
                        'categories' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'keyword'],
                                'name' => [
                                    'type' => 'text',
                                    'fields' => [
                                        'raw' => ['type' => 'keyword'],
                                    ],
                                ],
                            ],
                        ],
                        'language_attr' => ['type' => 'keyword'],
                        'published_at' => ['type' => 'date'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                        'blog_id' => ['type' => 'keyword'],
                        'category_id' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        ElasticSearch::indices()->create($params);

        Post::chunk(100, function ($posts) use (&$count) {
            $count++;
            $this->info($count);
            foreach ($posts as $post) {
                try {
                    Elasticsearch::index([
                        'id' => $post->id,
                        'index' => 'posts',
                        'body' => [
                            'id' => $post->id,
                            'blog_id' => $post->blog->id,
                            'uuid' => $post->uuid,
                            'slug' => $post->slug,
                            'content' => $post->content,
                            'excerpt' => $post->excerpt,
                            'status' => (bool) $post->status,
                            'featured_image' => is_array($post->featured_image) ? $post->featured_image : null,
                            'title' => $post->title,
                            'tags' => $post->tags->map(function ($tag) {
                                return [
                                    'id' => $tag->id,
                                    'name' => $tag->name,
                                ];
                            })->toArray(),
                            'author_uuid' => $post->author_uuid,
                            'visibility' => (bool) $post->visibility,
                            'postConfig' => $post->postConfig,
                            'metadata' => $post->metadata,
                            'category_id' => $post->category_id,
                            'category' => $post->category ? [
                                'id' => $post->category->id,
                                'name' => $post->category->name,
                            ] : null,
                            'categories' => $post->categories->map(function ($category) {
                                return [
                                    'id' => $category->id,
                                    'name' => $category->name,
                                    'uuid' => $category->uuid,
                                    'slug' => $category->slug,
                                ];
                            })->toArray(),
                            'language_attr' => $post->language_attr,
                            'published_at' => $post->published_at ? Carbon::parse($post->published_at)->toAtomString() : null,
                            'created_at' => $post->created_at->toAtomString(),
                            'updated_at' => $post->updated_at->toAtomString(),
                        ],
                    ]);
                } catch (Exception $e) {
                    $this->info($e->getMessage());
                }
            }
        });

        $this->info('Posts were successfully indexed');
    }
}
