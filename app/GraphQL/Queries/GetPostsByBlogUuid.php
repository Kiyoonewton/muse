<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Traits\PaginatedTrait;
use App\Models\Blog;
use App\Models\Post;
use Elasticsearch;

final class GetPostsByBlogUuid
{
    use PaginatedTrait;

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        $limit = $args['first'];
        $page = $args['page'] ?? 1;
        $filter = isset($args['filter']) ? $args['filter'] : null;
        $search = isset($args['search']) ? $args['search'] : null;
        $skip = ($page - 1) * $limit;
        $sort = isset($args['sort']) && count($args['sort']) > 0 ? $args['sort'][0] : null;

        $blog = Blog::firstWhere('uuid', $args['blog_uuid']);

        $params = '';

        if (! empty($search)) {
            $params = [
                'index' => 'posts',
                'body' => [
                    'from' => $skip,
                    'size' => $limit,
                    'query' => [
                        'function_score' => [
                            'query' => [
                                'bool' => [
                                    'must' => [
                                        [
                                            'term' => [
                                                'blog_id' => $blog->id,
                                            ],
                                        ],
                                        [
                                            'multi_match' => [
                                                'type' => 'best_fields',
                                                'query' => $search,
                                                'fields' => [
                                                    'title^10',
                                                    'content',
                                                    'excerpt^3',
                                                    'slug^2',
                                                ],
                                                'minimum_should_match' => '90%',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'functions' => [
                                [
                                    'gauss' => [
                                        'created_at' => [
                                            'origin' => now()->toIso8601String(),
                                            'scale' => '10d',
                                            'offset' => '1d',
                                            'decay' => 0.1,
                                        ],
                                    ],
                                    'weight' => 5,
                                ],
                            ],
                            'score_mode' => 'multiply',
                            'boost_mode' => 'multiply',
                        ],
                    ],
                ],
            ];
        } else {
            $params = [
                'index' => 'posts',
                'body' => [
                    'from' => $skip,
                    'size' => $limit,
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'term' => [
                                        'blog_id' => $blog->id,
                                    ],
                                ],
                            ],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'sort' => [],
                ],
            ];
        }

        if (! isset($args['include_drafts']) && ! $this->hasFilterForColumn($filter, 'status')) {
            $params['body']['query']['bool']['must'][] = [
                'term' => [
                    'status' => true,
                ],
            ];
        }

        if (! empty($filter)) {
            $this->addFiltersToQuery($params, $filter);
        }

        if (isset($args['isRandom'])) {
            $params['body']['sort'][] = [
                '_script' => [
                    'script' => 'Math.random()',
                    'type' => 'number',
                    'order' => 'asc',
                ],
            ];
        } else {
            if (null !== $sort) {
                $column = $sort['column'];
                $order = strtolower($sort['order']);
                $columnParts = explode('.', $column);

                if (count($columnParts) > 2) {
                    $nestedPath = $columnParts[0];

                    $params['body']['sort'][] = [
                        $column => [
                            'order' => $order,
                            'nested' => [
                                'path' => $nestedPath,
                            ],
                        ],
                    ];
                } else {
                    $params['body']['sort'][] = [
                        $column => [
                            'order' => $order,
                        ],
                    ];
                }
            } else {
                if (empty($search)) {
                    $params['body']['sort'][] = [
                        'created_at' => [
                            'order' => 'desc',
                        ],
                    ];
                }
            }
        }

        try {
            $response = ElasticSearch::search($params);
            $total = $response['hits']['total']['value'];
            $postIds = array_column($response['hits']['hits'], '_id');

            if (empty($postIds)) {
                return [
                    'data' => [],
                    'paginatorInfo' => null,
                ];
            }

            $posts = Post::whereIn('id', $postIds)->orderByRaw('FIELD(id, '.implode(',', $postIds).')')->get();

            $paginatorInfo = [
                'count' => count($posts),
                'currentPage' => $page,
                'hasMorePages' => ($total > $skip + $limit),
                'lastPage' => ceil($total / $limit),
                'perPage' => $limit,
                'total' => $total,
                'firstItem' => ($skip + 1),
                'lastItem' => ($skip + count($posts)),
            ];

            return [
                'data' => $posts,
                'paginatorInfo' => $paginatorInfo,
            ];
        } catch (\Exception $e) {
            return [
                'data' => [],
                'paginatorInfo' => null,
            ];
        }
    }
}
