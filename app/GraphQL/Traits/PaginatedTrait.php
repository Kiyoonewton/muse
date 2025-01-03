<?php

namespace App\GraphQL\Traits;

use App\GraphQL\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

trait PaginatedTrait
{
    public function getPaginatedCollection(
        Builder $builder,
        int $limit,
        int $page,
        ?string $search,
        ?array $sort,
        ?array $filter,
        ?array $searchFields
    ) {
        if (null !== $search && $search != '') {
            foreach ($searchFields as $key => $searchField) {
                $builder = ($key == 0) ? $builder->where($searchField, 'like', "%$search%") : $builder->orWhere($searchField, 'like', "%$search%", );
            }
        }

        if (! empty($filter)) {
            $builder = $this->applyFilters($builder, $filter);
        }

        // Execute the query and retrieve the results
        $post = $builder->get();

        $builder = (null !== $sort) ? $builder->orderBy($sort['column'], $sort['order']) : $builder->orderBy('created_at', 'desc');

        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        $paginatorInfo = $builder->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => $builder->get(),
            'paginatorInfo' => [
                'count' => $paginatorInfo->count(),
                'currentPage' => $paginatorInfo->currentPage(),
                'hasMorePages' => $paginatorInfo->lastPage() > $paginatorInfo->currentPage() ? true : false,
                'lastPage' => $paginatorInfo->lastPage(),
                'perPage' => $paginatorInfo->perPage(),
                'total' => $paginatorInfo->total(),
                'firstItem' => $paginatorInfo->firstItem(),
                'firstItem' => $paginatorInfo->lastItem(),
            ],
        ];
    }

    public function addFiltersToQuery(&$params, $filters)
    {
        foreach ($filters as $filter) {
            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            if ($column === 'status') {
                $value = (bool) $value;
            }
            if (strpos($column, '.') !== false) {
                $path = substr($column, 0, strpos($column, '.'));
                switch ($operator) {
                    case '=':
                        $params['body']['query']['bool']['must'][] = [
                            'nested' => [
                                'path' => $path,
                                'query' => [
                                    'match_phrase' => [
                                        $column => $value,
                                    ],
                                ],
                            ],
                        ];
                        break;
                    case '!=':
                        $params['body']['query']['bool']['must_not'][] = [
                            'nested' => [
                                'path' => $path,
                                'query' => [
                                    'match_phrase' => [
                                        $column => $value,
                                    ],
                                ],
                            ],
                        ];
                        break;
                    case 'like':
                        $params['body']['query']['bool']['must'][] = [
                            'nested' => [
                                'path' => $path,
                                'query' => [
                                    'match' => [
                                        $column => $value,
                                    ],
                                ],
                            ],
                        ];
                        break;
                    default:
                        continue 2;
                }
            } else {
                switch ($operator) {
                    case '=':
                        $params['body']['query']['bool']['must'][] = [
                            'match_phrase' => [
                                $column => $value,
                            ],
                        ];
                        break;
                    case '!=':
                        $params['body']['query']['bool']['must_not'][] = [
                            'match_phrase' => [
                                $column => $value,
                            ],
                        ];
                        break;
                    case '>':
                        $params['body']['query']['bool']['filter'][] = [
                            'range' => [
                                $column => [
                                    'gt' => $value,
                                ],
                            ],
                        ];
                        break;
                    case '>=':
                        $params['body']['query']['bool']['filter'][] = [
                            'range' => [
                                $column => [
                                    'gte' => $value,
                                ],
                            ],
                        ];
                        break;
                    case '<':
                        $params['body']['query']['bool']['filter'][] = [
                            'range' => [
                                $column => [
                                    'lt' => $value,
                                ],
                            ],
                        ];
                        break;
                    case '<=':
                        $params['body']['query']['bool']['filter'][] = [
                            'range' => [
                                $column => [
                                    'lte' => $value,
                                ],
                            ],
                        ];
                        break;
                    case 'like':
                        $params['body']['query']['bool']['must'][] = [
                            'match' => [
                                $column => $value,
                            ],
                        ];
                        break;
                    default:
                        continue 2;
                }
            }
        }
    }

    protected function applyFilters(Builder $builder, array $filters): Builder
    {
        foreach ($filters as $config) {
            if (! empty($config['column']) && ! empty($config['operator']) && isset($config['value'])) {
                // Special case for filtering by category.name using whereHas
                if ($config['column'] === 'categories.name') {
                    $builder = $builder->whereHas('category', function ($query) use ($config) {
                        if ($config['operator'] === '!=') {
                            $query->where('name', '!=', $config['value']);
                        } elseif ($config['operator'] === 'in') {
                            $query->whereIn('name', (array) $config['value']);
                        } elseif ($config['operator'] === 'like') {
                            $query->where('name', 'like', '%'.$config['value'].'%');
                        } else {
                            $query->where('name', $config['operator'], $config['value']);
                        }
                    });
                } elseif ($config['column'] === 'tags.name') {
                    $builder = $builder->whereHas('tags', function ($query) use ($config) {
                        // Apply the filtering based on the operator
                        if ($config['operator'] === '!=') {
                            $query->where('name', '!=', $config['value']);
                        } elseif ($config['operator'] === 'in') {
                            $query->whereIn('name', (array) $config['value']);
                        } elseif ($config['operator'] === 'like') {
                            $query->where('name', 'like', '%'.$config['value'].'%');
                        } else {
                            $query->where('name', $config['operator'], $config['value']);
                        }
                    });
                } else {
                    // Regular filtering for posts table columns
                    if ($config['operator'] === '!=') {
                        $builder = $builder->where($config['column'], '!=', $config['value']);
                    } elseif ($config['operator'] === 'in') {
                        $builder = $builder->whereIn($config['column'], (array) $config['value']);
                    } elseif ($config['operator'] === 'like') {
                        $builder = $builder->where($config['column'], 'like', '%'.$config['value'].'%');
                    } else {
                        $builder = $builder->where($config['column'], $config['operator'], $config['value']);
                    }
                }
            }
        }

        return $builder;
    }

    public function hasFilterForColumn($filters, $column)
    {
        foreach ($filters as $filter) {
            if ($filter['column'] === $column) {
                return true;
            }
        }

        return false;
    }
}
