<?php

namespace Tests\Feature;

use App\Models\{ Blog };
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Tests\TestCase;

class BlogActionsTest extends TestCase
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
     * User can Create Blog successfully test
     * @test
     * @return void
     */
    public function userCanCreateBlogSuccessfullyTest()
    {
        $domain = $this->faker->domainName.rand(0, 1000000);
        $site_uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createBlog($domain: String $site_uuid: ID )
            {
                createBlog(input: {domain: $domain site_uuid: $site_uuid })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain'=>$domain, 'site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'createBlog' => [
                    'uuid',
                    'domain',
                ],
            ],
        ]);
    }

    /**
     * User can not create blog without domain name test
     * @test
     * @return void
     */
    public function userCanNotCreateBlogWithoutDomainNameTest()
    {
        $domain = '';
        $site_uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createBlog($domain: String $site_uuid: ID )
            {
                createBlog(input: {domain: $domain site_uuid: $site_uuid })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain'=>$domain, 'site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.domain',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not create blog with domain name that already exist test
     * @test
     * @return void
     */
    public function userCanNotCreateBlogWithADomainNameThatExistTest()
    {
        $domain = Blog::inRandomOrder()->first()->domain;
        $site_uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createBlog($domain: String $site_uuid: ID )
            {
                createBlog(input: {domain: $domain site_uuid: $site_uuid })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain'=>$domain, 'site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.domain',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not create blog without site uuid test
     * @test
     * @return void
     */
    public function userCanNotCreateBlogWithoutSiteUuidTest()
    {
        $domain = $this->faker->domainName;
        $site_uuid = '';

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createBlog($domain: String $site_uuid: ID )
            {
                createBlog(input: {domain: $domain site_uuid: $site_uuid })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain'=>$domain, 'site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.site_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not create blog with a value other than uuid on site uuid field test
     * @test
     * @return void
     */
    public function userCanNotCreateSiteWithAValueOtherThanUuidOnSiteFieldTest()
    {
        $domain = $this->faker->domainName;
        $site_uuid = $this->faker->name;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createBlog($domain: String $site_uuid: ID )
            {
                createBlog(input: {domain: $domain site_uuid: $site_uuid })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain'=>$domain, 'site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.site_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not create blog with empty fields test
     * @test
     * @return void
     */
    public function userCanNotCreateBlogWithEmptyFieldsTest()
    {
        $domain = '';
        $site_uuid = '';

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createBlog($domain: String $site_uuid: ID )
            {
                createBlog(input: {domain: $domain site_uuid: $site_uuid })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain'=>$domain, 'site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.domain',
                            'input.site_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can delete blog successfully test
     * @test
     * @return void
     */
    public function userCanDeleteBlogSuccessfulyTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $uuid = $blog->uuid;
        $response = $this->graphQL(/** @lang GraphQL */'
            mutation deleteBlog($uuid: ID!)
            {
                deleteBlog(uuid: $uuid )
                {
                    uuid
                    domain
                }
            }
            ',
            ['uuid'=>$uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'deleteBlog' => [
                    'uuid',
                    'domain',
                ],
            ],
        ]);
    }

    /**
     * User can not delete blog that does not exist test
     * @test
     * @return void
     */
    public function userCanNotDeleteBlogThatDoesntExistTest()
    {
        $uuid = $this->faker->uuid;
        $response = $this->graphQL(/** @lang GraphQL */'
                mutation deleteBlog($uuid: ID!)
                {
                    deleteBlog(uuid: $uuid )
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['uuid'=>$uuid]
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
     * User can not delete blog with empty field test
     * @test
     * @return void
     */
    public function userCanNotDeleteBlogWithEmptyFieldTest()
    {
        $uuid = '';
        $response = $this->graphQL(/** @lang GraphQL */'
                mutation deleteBlog($uuid: ID!)
                {
                    deleteBlog(uuid: $uuid )
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['uuid'=>$uuid]
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
     * User can get blog by uuid successfully test
     * @test
     * @return void
     */
    public function userCanGetBlogByUuidSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $uuid = $blog->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
            query blogByUuid($uuid: ID!)
            {
                blogByUuid(uuid: $uuid )
                {
                    uuid
                    domain
                }
            }
            ',
            ['uuid'=>$uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'blogByUuid' => [
                    'uuid',
                    'domain',
                ],
            ],
        ]);
    }

    /**
     * User can not get blog by uuid with uuid that does not exist test
     * @test
     * @return void
     */
    public function userCanNotGetBlogByUuidThatDoesNotExistTest()
    {
        $uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
                query blogByUuid($uuid: ID!)
                {
                    blogByUuid(uuid: $uuid )
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['uuid' => $uuid]
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
     * User can not get blog by uuid with empty uuid field test
     * @test
     * @return void
     */
    public function userCanNotGetBlogByUuidWithEmptyUuidFieldTest()
    {
        $uuid = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                query blogByUuid($uuid: ID!)
                {
                    blogByUuid(uuid: $uuid )
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['uuid' => $uuid]
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
     * User can get blog by domain successfully test
     * @test
     * @return void
     */
    public function userCanGetBlogByDomainSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $domain = $blog->domain;

        $response = $this->graphQL(/** @lang GraphQL */'
            query blogByDomain($domain: String!)
            {
                getBlogByDomain(input: {domain: $domain })
                {
                    uuid
                    domain
                }
            }
            ',
            ['domain' => $domain]
        );

        $response->assertJsonStructure([
            'data' => [
                'getBlogByDomain' => [
                    'uuid',
                    'domain',
                ],
            ],
        ]);
    }

    /**
     * User can not get blog without domain name test
     * @test
     * @return void
     */
    public function userCanNotGetBlogWithoutDomainNameTest()
    {
        $domain = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                query blogByDomain($domain: String!)
                {
                    getBlogByDomain(input: {domain: $domain })
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['domain' => $domain]
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
     * User can not get blog with invalid domain name test
     * @test
     * @return void
     */
    public function userCanNotGetBlogWithInvalidDomainTest()
    {
        $domain = $this->faker->word.rand(0, 1000000);

        $response = $this->graphQL(/** @lang GraphQL */'
                query blogByDomain($domain: String!)
                {
                    getBlogByDomain(input: {domain: $domain })
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['domain' => $domain]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.domain',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can get blog by site uuid successfully test
     * @test
     * @return void
     */
    public function userCanGetBlogBySiteUuidSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $site_uuid = $blog->site_uuid;
        $response = $this->graphQL(/** @lang GraphQL */'
            query blogBySiteUuid($site_uuid: ID)
            {
                getBlogBySiteUuid(site_uuid: $site_uuid )
                {
                    uuid
                    domain
                }
            }
            ',
            ['site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'getBlogBySiteUuid' => [
                    'uuid',
                    'domain',
                ],
            ],
        ]);
    }

    /**
     * User can get blog without a valid site uuid test
     * @test
     * @return void
     */
    public function userCanNotGetBlogWithoutAValidSiteUuidTest()
    {
        $site_uuid = $this->faker->uuid;
        $response = $this->graphQL(/** @lang GraphQL */'
                query blogBySiteUuid($site_uuid: ID)
                {
                    getBlogBySiteUuid(site_uuid: $site_uuid )
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'site_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can get blog without site uuid field test
     * @test
     * @return void
     */
    public function userCanNotGetBlogWithoutSiteUuidFieldTest()
    {
        $site_uuid = '';
        $response = $this->graphQL(/** @lang GraphQL */'
                query blogBySiteUuid($site_uuid: ID)
                {
                    getBlogBySiteUuid(site_uuid: $site_uuid )
                    {
                        uuid
                        domain
                    }
                }
                ',
            ['site_uuid' => $site_uuid]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'site_uuid',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
