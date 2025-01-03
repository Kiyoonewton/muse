<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Tests\TestCase;

class TagsActionsTest extends TestCase
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
     * user can create tag successfully test
     * @test
     * @return void
     */
    public function userCanCreateTagSuccessfullyTest()
    {
        $name = $this->faker->word.rand(0, 1000000);

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createTag( $name: String! )
            {
                createTag(name: $name )
                {
                    name
                }
            }
            ',
            ['name'=>$name]
        );

        $response->assertJsonStructure([
            'data' => [
                'createTag' => [
                    'name',
                ],
            ],
        ]);

        $response_name = $response->json('data.*.name');
        $this->assertSame([$name], $response_name);
    }

    /**
     * user can not create tag without name test
     * @test
     * @return void
     */
    public function userCanNotCreateTageWithoutNameTest()
    {
        $name = '';

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createTag( $name: String! )
            {
                createTag(name: $name )
                {
                    name
                }
            }
            ',
            ['name'=>$name]
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
     * user can get tags by blog uuid
     * @test
     * @return void
     */
    public function userCanGetTagsByBlogUuid()
    {
        $blog = Blog::inRandomOrder()->first();

        $response = $this->graphQL(/** @lang GraphQL */'
            query getTagsByBlogUuid($blog_uuid: ID)
            {
                getTagsByBlogUuid(input: { blog_uuid: $blog_uuid })
                {
                    data {
                        name
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
                'getTagsByBlogUuid' => [
                    'data',
                    'paginatorInfo' => [
                        'currentPage',
                        'lastPage',
                    ],
                ],
            ],
        ]);
    }

    /**
     * user can get tags by blog uuid
     * @test
     * @return void
     */
    public function userCannotGetTagsWithInvalidBlogUuid()
    {
        $response = $this->graphQL(/** @lang GraphQL */'
            query getTagsByBlogUuid($blog_uuid: ID)
            {
                getTagsByBlogUuid(input: { blog_uuid: $blog_uuid })
                {
                    data {
                        name
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
