<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Tests\TestCase;

class PostActionsTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();
    }

    /**
     * User can Create post with minimum information successfully test
     * @test
     * @return void
     */
    public function userCanCreatePostWithMinimumInformationSuccessfullyTest()
    {
        $blog_uuid = Blog::inRandomOrder()->first()->uuid;
        $author_uuid = $this->faker->uuid;
        $status = true;
        $slug = $this->faker->slug;
        $title = $this->faker->sentence;
        $content = $this->faker->randomHtml(5, 6);
        $category_uuids = Category::inRandomOrder()->limit(4)->pluck('uuid');

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            mutation createPost(
                $title: String!
                $slug: String!
                $blog_uuid: ID!
                $author_uuid: ID!
                $status: Boolean!
                $category_uuids: [ID]
                $content: String!
            )
            {
                createPost(
                    input: {
                        title: $title
                        slug: $slug
                        blog_uuid: $blog_uuid
                        author_uuid: $author_uuid
                        status: $status
                        category_uuids: $category_uuids
                        content: $content
                    }
                )
                {
                    title
                    uuid
                }
            }
            ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'createPost' => [
                    'uuid',
                    'title',
                ],
            ],
        ]);
    }

    /**
     * User can Create post with maximum information successfully test
     * @test
     * @return void
     */
    public function userCanCreatePostWithMaximumInformationSuccessfullyTest()
    {
        $blog_uuid = Blog::inRandomOrder()->first()->uuid;
        $author_uuid = $this->faker->uuid;
        $status = true;
        $slug = $this->faker->slug;
        $title = $this->faker->sentence;

        $category_uuids = Category::inRandomOrder()->limit(4)->pluck('uuid');
        $content = $this->faker->randomHtml(5, 6);
        $visibility = $this->faker->randomElement([true, false]);
        $tags = $this->faker->randomElement([$this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000)]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $blog_uuid: ID!
                    $author_uuid: ID!
                    $status: Boolean!
                    $category_uuids: [ID]
                    $content: String
                    $visibility: Boolean
                    $tags: [String]
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                blog_uuid: $blog_uuid
                                author_uuid: $author_uuid
                                status: $status
                                category_uuids: $category_uuids
                                content: $content
                                visibility: $visibility
                                tags: $tags
                            }
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
                'visibility' => $visibility,
                'tags' => $tags,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'createPost' => [
                    'uuid',
                    'title',
                    'content',
                    'status',
                    'slug',
                ],
            ],
        ]);
    }

    /**
     * User can not Create post without post title test
     * @test
     * @return void
     */
    public function userCanNotCreatePostWithoutPostTitleTest()
    {
        $blog_uuid = Blog::inRandomOrder()->first()->uuid;
        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->word.rand(0, 1000000);
        $content = $this->faker->randomHtml(5, 6);
        $title = '';

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $blog_uuid: ID!
                    $author_uuid: ID!
                    $status: Boolean!
                    $content: String!
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                blog_uuid: $blog_uuid
                                author_uuid: $author_uuid
                                status: $status
                                content: $content
                            }
                        )
                    {
                        title
                        uuid
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'content' => $content,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not Create post without blog test
     * @test
     * @return void
     */
    public function userCanNotCreatePostWithoutBlogTest()
    {
        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->word.rand(0, 1000000);
        $title = $this->faker->sentence;
        $blog_uuid = $this->faker->uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $author_uuid: ID!
                    $status: Boolean!
                    $blog_uuid: ID!
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                author_uuid: $author_uuid
                                status: $status
                                blog_uuid: $blog_uuid
                            }
                        )
                    {
                        title
                        uuid
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'blog_uuid' => $blog_uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not Create post without author test
     * @test
     * @return void
     */
    public function userCanNotCreatePostWithoutAuthorTest()
    {
        $blog_uuid = Blog::inRandomOrder()->first()->uuid;
        $author_uuid = '';
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->word.rand(0, 1000000);
        $title = $this->faker->sentence;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $blog_uuid: ID!
                    $author_uuid: ID!
                    $status: Boolean!
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                blog_uuid: $blog_uuid
                                author_uuid: $author_uuid
                                status: $status
                            }
                        )
                    {
                        title
                        uuid
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not Create post without slug test
     * @test
     * @return void
     */
    public function userCanNotCreatePostWithoutSlugTest()
    {
        $blog_uuid = Blog::inRandomOrder()->first()->uuid;
        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = '';
        $title = $this->faker->sentence;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $blog_uuid: ID!
                    $author_uuid: ID!
                    $status: Boolean!
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                blog_uuid: $blog_uuid
                                author_uuid: $author_uuid
                                status: $status
                            }
                        )
                    {
                        title
                        uuid
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not Create post with a category that does not exist test
     * @test
     * @return void
     */
    public function userCanNotCreatePostWithCategoryThatDoesNotExistTest()
    {
        $blog_uuid = Blog::inRandomOrder()->first()->uuid;
        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->word.rand(0, 1000000);
        $title = $this->faker->sentence;

        $category_uuids = [$this->faker->uuid, $this->faker->uuid];
        $content = $this->faker->randomHtml(5, 6);
        $visibility = $this->faker->randomElement([true, false]);
        $tags = $this->faker->randomElement([$this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000)]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $blog_uuid: ID!
                    $author_uuid: ID!
                    $status: Boolean!
                    $category_uuids: [ID]
                    $content: String
                    $visibility: Boolean
                    $tags: [String]
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                blog_uuid: $blog_uuid
                                author_uuid: $author_uuid
                                status: $status
                                category_uuids: $category_uuids
                                content: $content
                                visibility: $visibility
                                tags: $tags
                            }
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
                'visibility' => $visibility,
                'tags' => $tags,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.category_uuids',
                        ],

                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not Create post with a slug that already exist in the same blog test
     * @test
     * @return void
     */
    public function userCanNotCreatePostWithASlugThatAlreadyExistTest()
    {
        $blog = Blog::inRandomOrder()
        ->whereHas('posts', function ($query) {
            $query->whereNotNull('slug');
        })->first();
        $category_uuids = Category::inRandomOrder()->limit(2)->pluck('uuid');
        $blog_uuid = $blog->uuid;

        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $post = $blog->posts
        ->whereNotNull('slug')
        ->first();

        $slug = $post->slug;
        $title = $this->faker->sentence;

        $content = $this->faker->randomHtml(5, 6);
        $visibility = $this->faker->randomElement([true, false]);
        $tags = $this->faker->randomElement([$this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000)]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation createPost(
                    $title: String!
                    $slug: String!
                    $blog_uuid: ID!
                    $author_uuid: ID!
                    $status: Boolean!
                    $category_uuids: [ID]
                    $content: String
                    $visibility: Boolean
                    $tags: [String]
                    )
                {
                    createPost(
                        input:
                            {
                                title: $title
                                slug: $slug
                                blog_uuid: $blog_uuid
                                author_uuid: $author_uuid
                                status: $status
                                category_uuids: $category_uuids
                                content: $content
                                visibility: $visibility
                                tags: $tags
                            }
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'title' => $title,
                'slug' => $slug,
                'blog_uuid' => $blog_uuid,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
                'visibility' => $visibility,
                'tags' => $tags,
            ]
        );

        Log::info($response->json());

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation',
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can update post successfully test
     * @test
     * @return void
     */
    public function userCanUpdateSuccessfullyTest()
    {
        $blog = Blog::whereHas('posts', function ($query) {
            $query->whereNotNull('title');
        })->first();
        $post = $blog->posts()
        ->whereNotNull('title')
        ->first();
        $uuid = $post->uuid;
        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->slug;
        $title = $this->faker->sentence;

        $category_uuids = Category::inRandomOrder()->limit(4)->pluck('uuid');
        $content = $this->faker->randomHtml(5, 6);
        $visibility = $this->faker->randomElement([true, false]);
        $tags = $this->faker->randomElement([$this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000)]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation updatePost(
                    $uuid: ID!
                    $title: String!
                    $slug: String!
                    $author_uuid: ID!
                    $status: Boolean!
                    $category_uuids: [ID]
                    $content: String
                    $visibility: Boolean
                    $tags: [String!]
                    )
                {
                    updatePost(
                        input: {
                            uuid: $uuid
                            title: $title
                            slug: $slug
                            author_uuid: $author_uuid
                            status: $status
                            category_uuids: $category_uuids
                            content: $content
                            visibility: $visibility
                            tags: $tags
                        }
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'uuid' => $uuid,
                'title' => $title,
                'slug' => $slug,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
                'visibility' => $visibility,
                'tags' => $tags,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'updatePost' => [
                    'uuid',
                    'title',
                    'content',
                    'status',
                    'slug',
                ],
            ],
        ]);
    }

    /**
     * User can not update post that does not exist test
     * @test
     * @return void
     */
    public function userCanNotUpdatePostThatDoesNotExistTest()
    {
        $uuid = $this->faker->uuid;
        $author_uuid = $this->faker->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->slug;
        $title = $this->faker->sentence;

        $category_uuids = Category::inRandomOrder()->limit(4)->pluck('uuid');
        $content = $this->faker->randomHtml(5, 6);
        $visibility = $this->faker->randomElement([true, false]);
        $tags = $this->faker->randomElement([$this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000)]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    mutation updatePost(
                        $uuid: ID!
                        $title: String!
                        $slug: String!
                        $author_uuid: ID!
                        $status: Boolean!
                        $category_uuids: [ID]
                        $content: String
                        $visibility: Boolean
                        $tags: [String!]
                        )
                    {
                        updatePost(
                            input: {
                                uuid: $uuid
                                title: $title
                                slug: $slug
                                author_uuid: $author_uuid
                                status: $status
                                category_uuids: $category_uuids
                                content: $content
                                visibility: $visibility
                                tags: $tags
                            }
                            )
                        {
                            title
                            uuid
                            content
                            status
                            slug
                        }
                    }
                    ',
            [
                'uuid' => $uuid,
                'title' => $title,
                'slug' => $slug,
                'author_uuids' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
                'visibility' => $visibility,
                'tags' => $tags,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not update post with invalid category test
     * @test
     * @return void
     */
    public function userCanNotUpdatePostWithInvalidCategoryTest()
    {
        $post = Post::inRandomOrder()->first();
        $uuid = $post->uuid;
        $status = $this->faker->randomElement([true, false]);
        $slug = $this->faker->slug;
        $title = $this->faker->sentence;

        $category_uuids = [$this->faker->uuid, $this->faker->uuid];
        $content = $this->faker->randomHtml(5, 6);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation updatePost(
                    $uuid: ID!
                    $title: String
                    $slug: String
                    $status: Boolean
                    $category_uuids: [ID]
                    $content: String
                    )
                {
                    updatePost(
                        input: {
                            uuid: $uuid
                            title: $title
                            slug: $slug
                            status: $status
                            category_uuids: $category_uuids
                            content: $content
                        }
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'uuid' => $uuid,
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not update post slug to an existing slug test
     * @test
     * @return void
     */
    public function userCanNotUpdatePostSlugToAnExistingSlugTest()
    {
        $blog = Blog::inRandomOrder()
        ->whereHas('posts', function ($query) {
            $query->whereNotNull('slug');
        })
        ->first();
        $post = $blog->posts
        ->whereNotNull('slug')
        ->first();

        $blogPost = Post::create([
            'title' => $this->faker->word.rand(0, 1000000),
            'blog_id' => $blog->id,
            'author_uuid' => $this->faker->uuid,
        ]);

        $uuid = $blogPost->uuid;
        $author_uuid = $this->faker->uuid;
        $status = true;
        $slug = $post->slug;
        $title = $this->faker->sentence;

        $category_uuids = Category::inRandomOrder()->limit(4)->pluck('uuid');
        $content = $this->faker->randomHtml(5, 6);
        $visibility = $this->faker->randomElement([true, false]);
        $tags = $this->faker->randomElement([$this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000), $this->faker->word.rand(0, 1000000)]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation updatePost(
                    $uuid: ID!
                    $title: String!
                    $slug: String!
                    $author_uuid: ID!
                    $status: Boolean!
                    $category_uuids: [ID]
                    $content: String
                    $visibility: Boolean
                    $tags: [String!]
                    )
                {
                    updatePost(
                        input: {
                            uuid: $uuid
                            title: $title
                            slug: $slug
                            author_uuid: $author_uuid
                            status: $status
                            category_uuids: $category_uuids
                            content: $content
                            visibility: $visibility
                            tags: $tags
                        }
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'uuid' => $uuid,
                'title' => $title,
                'slug' => $slug,
                'author_uuid' => $author_uuid,
                'status' => $status,
                'category_uuids' => $category_uuids,
                'content' => $content,
                'visibility' => $visibility,
                'tags' => $tags,
            ]
        );

        Log::info($response->json());

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.slug',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can delete post successfully test
     * @test
     * @return void
     */
    public function userCanDeletePostSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->has('posts', '>', 1)->first();
        $post = $blog->posts[0];
        $uuid = $post->uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                mutation deletePost(
                    $uuid: ID!
                    )
                {
                    deletePost(
                            uuid: $uuid
                        )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'uuid' => $uuid,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'deletePost' => [
                    'uuid',
                    'title',
                    'content',
                    'status',
                    'slug',
                ],
            ],
        ]);
    }

    /**
     * User can not delete post that does not exist test
     * @test
     * @return void
     */
    public function userCanNotDeletePostThatDoestNotExist()
    {
        $uuid = $this->faker->uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    mutation deletePost(
                        $uuid: ID!
                        )
                    {
                        deletePost(
                                uuid: $uuid
                            )
                        {
                            title
                            uuid
                            content
                            status
                            slug
                        }
                    }
                    ',
            [
                'uuid' => $uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not delete post using empty uuid field test
     * @test
     * @return void
     */
    public function userCanNotDeletePostUsingEmptyUuidFieldTest()
    {
        $uuid = '';

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    mutation deletePost(
                        $uuid: ID!
                        )
                    {
                        deletePost(
                                uuid: $uuid
                            )
                        {
                            title
                            uuid
                            content
                            status
                            slug
                        }
                    }
                    ',
            [
                'uuid' => $uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can get post by uuid successfully test
     * @test
     * @return void
     */
    public function userCanGetPostByUuidSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()
        ->whereHas('posts', function ($query) {
            $query->where('status', 1);
        })->first();
        $post = $blog->posts->where('status', 1)->first();
        $uuid = $post->uuid;
        $blog_uuid = $post->blog->uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query postByUuid(
                $uuid: ID!
                $blog_uuid: ID!
                )
            {
                getPostByUuid(
                        input: {
                            uuid: $uuid
                            blog_uuid: $blog_uuid
                        }
                    )
                {
                    title
                    uuid
                    content
                    status
                    slug
                }
            }
            ',
            [
                'uuid' => $uuid,
                'blog_uuid' => $blog_uuid,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'getPostByUuid' => [
                    'uuid',
                    'title',
                    'content',
                    'status',
                    'slug',
                ],
            ],
        ]);
    }

    /**
     * User can not get post that does not exist test
     * @test
     * @return void
     */
    public function userCanNotGetPostThatDoesNotExistTest()
    {
        $uuid = $this->faker->uuid;
        $blog_uuid = $this->faker->uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                query postByUuid(
                    $uuid: ID!
                    $blog_uuid: ID!
                    )
                {
                    getPostByUuid(
                        input: {
                            uuid: $uuid
                            blog_uuid: $blog_uuid
                        }
                    )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'uuid' => $uuid,
                'blog_uuid' => $blog_uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not get post with empty uuid field test
     * @test
     * @return void
     */
    public function userCanNotGetPostWithEmptyUuidFieldTest()
    {
        $uuid = '';
        $blog_uuid = '';

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                query postByUuid(
                    $uuid: ID!
                    $blog_uuid: ID!
                    )
                {
                   getPostByUuid(
                        input: {
                            uuid: $uuid
                            blog_uuid: $blog_uuid
                        }
                    )
                    {
                        title
                        uuid
                        content
                        status
                        slug
                    }
                }
                ',
            [
                'uuid' => $uuid,
                'blog_uuid' => $blog_uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can get post by blog uuid and slug successfully test
     * @test
     * @return void
     */
    public function userCanGetPostByBlogUuidAndSlugSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->whereHas('posts', function ($q) {
            $q->where('status', 1);
        })->first();

        $post = $blog->posts()->where('status', 1)->first();
        $uuid = $blog->uuid;
        $slug = $post->slug;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query getPostByBlogUuidAndSlug(
                $blog_uuid: ID!
                $slug: String!
                )
            {
                getPostByBlogUuidAndSlug(
                    input: {
                        blog_uuid: $blog_uuid
                        slug: $slug
                    }
                    )
                {
                    title
                    content
                    status
                    slug
                }
            }
            ',
            [
                'blog_uuid' => $uuid,
                'slug' => $slug,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'getPostByBlogUuidAndSlug' => [],
            ],
        ]);
    }

    /**
     * User can not get post by blog uuid and slug with invalid uuid test
     * @test
     * @return void
     */
    public function userCanNotGetPostByBlogUuidAndSlugWithInvalidBlogUuidTest()
    {
        $uuid = $this->faker->uuid;
        $slug = $this->faker->slug;
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    query getPostByBlogUuidAndSlug(
                        $blog_uuid: ID!
                        $slug: String!
                        )
                    {
                        getPostByBlogUuidAndSlug(
                            input: {
                                blog_uuid: $blog_uuid
                                slug: $slug
                            }
                            )
                        {
                            title
                            uuid
                            content
                            status
                            slug
                        }
                    }
                    ',
            [
                'blog_uuid' => $uuid,
                'slug' => $slug,
            ]
        );
        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.blog_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not get post by blog uuid and slug with empty uuid field test
     * @test
     * @return void
     */
    public function userCanNotGetPostByBlogUuidAndSlugWithEmptyUuidFieldsTest()
    {
        $uuid = '';
        $slug = $this->faker->slug;
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    query getPostByBlogUuidAndSlug(
                        $blog_uuid: ID!
                        $slug: String!
                        )
                    {
                        getPostByBlogUuidAndSlug(
                            input: {
                                blog_uuid: $blog_uuid
                                slug: $slug
                            }
                            )
                        {
                            title
                            uuid
                            content
                            status
                            slug
                        }
                    }
                    ',
            [
                'blog_uuid' => $uuid,
                'slug' => $slug,
            ]
        );
        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can not get post by blog uuid and slug with empty slug field test
     * @test
     * @return void
     */
    public function userCanNotGetPostByBlogUuidAndSlugWithEmptySlugFieldsTest()
    {
        $blog = Blog::inRandomOrder()->first();
        $uuid = $blog->uuid;
        $slug = '';
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query getPostByBlogUuidAndSlug(
                $blog_uuid: ID!
                $slug: String!
                )
            {
                getPostByBlogUuidAndSlug(
                    input: {
                        blog_uuid: $blog_uuid
                        slug: $slug
                    }
                    )
                {
                    title
                    uuid
                    content
                    status
                    slug
                }
            }
            ',
            [
                'blog_uuid' => $uuid,
                'slug' => $slug,
            ]
        );
        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }

    /**
     * User can get post by Category Uuid successfully test
     * @test
     * @return void
     */
    public function userCanGetPostWithCategoryUuidSuccessfullyTest()
    {
        $category = Category::inRandomOrder()
        ->whereHas('posts', function ($query) {
            $query->where('status', 1);
        })->first();
        $category_uuid = $category->uuid;
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                query getPostsByCategoryUuid(
                    $category_uuid: ID
                    )
                {
                    getPostsByCategoryUuid(
                        input: {
                            category_uuid: $category_uuid
                        }
                        )
                    {
                        data {
                            title
                            uuid
                            content
                            status
                            slug
                        }
                    }
                }
                ',
            [
                'category_uuid' => $category_uuid,
            ]
        );

        $response->assertJsonStructure([
            'data' => [
                'getPostsByCategoryUuid' => [
                    'data',
                ],
            ],
        ]);
    }

    /**
     * User can not get post by Category id with invalid category uuid test
     * @test
     * @return void
     */
    public function userCanNotGetPostWithInvalidCategoryUuidTest()
    {
        $category_uuid = $this->faker->uuid;
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    query getPostsByCategoryUuid(
                        $category_uuid: ID
                        )
                    {
                        getPostsByCategoryUuid(
                            input: {
                                category_uuid: $category_uuid
                            }
                            )
                        {
                           data {
                                title
                                uuid
                                content
                                status
                                slug
                            }
                        }
                    }
                    ',
            [
                'category_uuid' => $category_uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.category_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not get post by Category id with empty field test
     * @test
     * @return void
     */
    public function userCanNotGetPostWithEmptyFieldTest()
    {
        $category_uuid = '';
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                    query getPostsByCategoryUuid(
                        $category_uuid: ID
                        )
                    {
                        getPostsByCategoryUuid(
                            input: {
                                category_uuid: $category_uuid
                            }
                            )
                        {
                           data {
                                title
                                uuid
                                content
                                status
                                slug
                            }
                        }
                    }
                    ',
            [
                'category_uuid' => $category_uuid,
            ]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.category_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can get post by Author uuid successfully test
     * @test
     * @return void
     */
    public function userCanGetPostsByAuthorUuidSuccessfullyTest()
    {
        $post = Post::inRandomOrder()->first();

        $author_uuid = $post->author_uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query getPostsByAuthorUuid(
                $author_uuid: ID
                )
            {
                getPostsByAuthorUuid(
                    input: {
                        author_uuid: $author_uuid
                    }
                    )
                {
                    data {
                        title
                        uuid
                        content
                    }
                }
            }
            ',
            ['author_uuid' => $author_uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'getPostsByAuthorUuid' => [
                    'data' => [
                        [
                            'uuid',
                            'title',
                            'content',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not get post by Author uuid with invalid uuid test
     * @test
     * @return void
     */
    public function userCanNotGetPostsByAuthorUuidWithInvalidUuidTest()
    {
        $author_uuid = $this->faker->uuid;

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                query getPostsByAuthorUuid(
                    $author_uuid: ID
                    )
                {
                    getPostsByAuthorUuid(
                        input: {
                            author_uuid: $author_uuid
                        }
                        )
                    {
                       data {
                            title
                            uuid
                            content
                        }
                    }
                }
                ',
            ['author_uuid' => $author_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.author_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not get post by Author uuid with empty author uuid test
     * @test
     * @return void
     */
    public function userCanNotGetPostsByAuthorUuidWithEmptyFieldTest()
    {
        $author_uuid = '';

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
                query getPostsByAuthorUuid(
                    $author_uuid: ID
                    )
                {
                    getPostsByAuthorUuid(
                        input: {
                            author_uuid: $author_uuid
                        }
                        )
                    {
                       data {
                            title
                            uuid
                            content
                        }
                    }
                }
                ',
            ['author_uuid' => $author_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' =>[
                            'input.author_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can get posts by blog uuid
     * @test
     * @return void
     */
    public function userCanGetPostsByBlogUuid()
    {
        $blog = Blog::inRandomOrder()
        ->whereHas('posts', function ($query) {
            $query->where('status', 1);
        })->first();

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query getPostsByBlogUuid(
                $blog_uuid: ID
                )
            {
                getPostsByBlogUuid(
                    input: {
                        blog_uuid: $blog_uuid
                    }
                    )
                {
                    data {
                        uuid
                        title
                        slug
                    }
                    paginatorInfo {
                        currentPage
                        lastPage
                    }
                }
            }
            ',
            ['blog_uuid' => $blog->uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'getPostsByBlogUuid' => [
                    'data',
                    'paginatorInfo',
                ],
            ],
        ]);
    }

    /**
     * User cannot get posts with an invalid blog uuid
     * @test
     * @return void
     */
    public function userCannotGetPostsWithInvalidBlogUuid()
    {
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query getPostsByBlogUuid(
                $blog_uuid: ID
                )
            {
                getPostsByBlogUuid(
                    input: {
                        blog_uuid: $blog_uuid
                    }
                    )
                {
                    data {
                        uuid
                        title
                        slug
                    }
                    paginatorInfo {
                        currentPage
                        lastPage
                    }
                }
            }
            ',
            ['blog_uuid' => $this->faker->uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.blog_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
