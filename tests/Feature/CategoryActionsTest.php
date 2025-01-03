<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Tests\TestCase;

class CategoryActionsTest extends TestCase
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
     * User can Create Category successfully test
     * @test
     * @return void
     */
    public function userCanCreateCategorySuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $name = $this->faker->word.rand(0, 1000000);
        $slug = $this->faker->word.rand(0, 1000000);
        $blog_uuid = $blog->uuid;
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
            {
                createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
        );

        $response->assertJsonStructure([
            'data' => [
                'createCategory' => [
                    'uuid',
                    'name',
                ],
            ],
        ]);
    }

    /**
     * User can not Create category without name test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithoutNameTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $name = '';
        $slug = $this->faker->word.rand(0, 1000000);
        $blog_uuid = $blog->uuid;
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
            {
                createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
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
     * User can not Create category without slug test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithoutSlugTest()
    {
        $blog = Blog::inRandomOrder()->first();

        $name = $this->faker->word.rand(0, 1000000);
        $slug = '';
        $blog_uuid = $blog->uuid;
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
            {
                createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
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
     * User can not Create category without Blog uuid test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithoutBlogUuidTest()
    {
        $name = $this->faker->word.rand(0, 1000000);
        $slug = $this->faker->word.rand(0, 1000000);
        $blog_uuid = '';
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
                mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
                {
                    createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                    {
                        uuid
                        name
                    }
                }
                ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
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
     * User can not Create category without slug test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithInvalidBlogUuidTest()
    {
        $name = $this->faker->word.rand(0, 1000000);
        $slug = $this->faker->word.rand(0, 1000000);
        $blog_uuid = $this->faker->uuid;
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
                mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
                {
                    createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                    {
                        uuid
                        name
                    }
                }
                ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'reason',
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not Create category with an existing name test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithAnExistingNameTest()
    {
        $blog = Blog::inRandomOrder()->has('categories', '>', 1)->first();
        $category = $blog->categories[0];
        $name = $category->name;
        $slug = $this->faker->word.rand(0, 1000000);

        $blog_uuid = $blog->uuid;
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
            {
                createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.name',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not Create category with and existing slug test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithAnExistingSlugTest()
    {
        $blog = Blog::inRandomOrder()->has('categories', '>', 1)->first();
        $category = $blog->categories[0];

        $blog = $category->blog;
        $name = $this->faker->word.rand(0, 1000000);
        $slug = $category->slug;
        $blog_uuid = $blog->uuid;
        $description = $this->faker->text;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
            {
                createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
        );

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
     * User can not Create category with empty fields test
     * @test
     * @return void
     */
    public function userCanNotCreateCategoryWithEmptyFields()
    {
        $name = '';
        $slug = '';
        $blog_uuid = '';
        $description = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                    mutation createCategory($name: String! $slug: String! $blog_uuid: ID! $description: String)
                    {
                        createCategory(input: {name: $name slug: $slug blog_uuid: $blog_uuid description: $description })
                        {
                            uuid
                            name
                        }
                    }
                    ',
            ['name'=>$name, 'slug' => $slug, 'blog_uuid' => $blog_uuid, 'description'=> $description]
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
     * User can Update Category successfully test
     * @test
     * @return void
     */
    public function userCanUpdateCategorySuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->has('categories', '>', 1)->first();
        $category = $blog->categories[0];
        $name = $this->faker->word.rand(0, 1000000);
        $slug = $this->faker->word.rand(0, 1000000);
        $description = $this->faker->word;

        $uuid = $category->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation updateCategory($name: String $slug: String $uuid: ID! $description: String)
            {
                updateCategory(input: {name: $name slug: $slug uuid: $uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'uuid' => $uuid, 'description'=> $description]
        );

        Log::info($response->json());

        $response->assertJsonStructure([
            'data' => [
                'updateCategory' => [
                    'uuid',
                    'name',
                ],
            ],
        ]);
    }

    /**
     * User can not Update Category without category uuid test
     * @test
     * @return void
     */
    public function userCanNotUpdateCategoryWithoutCategoryUuidTest()
    {
        $name = $this->faker->word.rand(0, 1000000);
        $slug = $this->faker->word.rand(0, 1000000);
        $description = $this->faker->text;
        $uuid = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                mutation updateCategory($name: String $slug: String $uuid: ID! $description: String)
                {
                    updateCategory(input: {name: $name slug: $slug uuid: $uuid description: $description })
                    {
                        uuid
                        name
                    }
                }
                ',
            ['name'=>$name, 'slug' => $slug, 'uuid' => $uuid, 'description'=> $description]
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
     * User can not Update Category name with an existing name in thesame blog test
     * @test
     * @return void
     */
    public function userCanNotUpdateCategoryNameWithAnExistingNameInThesameBlogTest()
    {
        $blog = Blog::inRandomOrder()->has('categories', '>', 1)->first();
        $category = $blog->categories[0];
        $existingCategory = Category::create([
            'name' => $this->faker->word.rand(0, 1000000),
            'slug' => '/'.$this->faker->word.rand(0, 1000000),
            'blog_id' => $category->blog_id,
        ]);

        $slug = $this->faker->word.rand(0, 1000000);
        $description = $this->faker->text;
        $uuid = $category->uuid;
        $name = $existingCategory->name;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation updateCategory($name: String $slug: String $uuid: ID! $description: String)
            {
                updateCategory(input: {name: $name slug: $slug uuid: $uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'uuid' => $uuid, 'description'=> $description]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.name',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User can not Update Category slug with an existing slug in thesame blog test
     * @test
     * @return void
     */
    public function userCanNotUpdateCategorySlugWithAnExistingSlugInThesameBlogTest()
    {
        $blog = Blog::inRandomOrder()->has('categories', '>', 1)->first();
        $category = $blog->categories[0];
        $existingCategory = Category::create([
            'name' => $this->faker->word.rand(0, 1000000),
            'slug' => '/'.$this->faker->word.rand(0, 1000000),
            'blog_id' => $category->blog_id,
        ]);

        $slug = $existingCategory->slug;
        $description = $this->faker->text;
        $uuid = $category->uuid;
        $name = $this->faker->word.rand(0, 1000000);

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation updateCategory($name: String $slug: String $uuid: ID! $description: String)
            {
                updateCategory(input: {name: $name slug: $slug uuid: $uuid description: $description })
                {
                    uuid
                    name
                }
            }
            ',
            ['name'=>$name, 'slug' => $slug, 'uuid' => $uuid, 'description'=> $description]
        );

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
     * User can not Update Category empty fields test
     * @test
     * @return void
     */
    public function userCanNotUpdateCategoryWithEmptyFields()
    {
        $category = Category::inRandomOrder()->first();
        $slug = '';
        $description = '';
        $uuid = '';
        $name = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                mutation updateCategory($name: String $slug: String $uuid: ID! $description: String)
                {
                    updateCategory(input: {name: $name slug: $slug uuid: $uuid description: $description })
                    {
                        uuid
                        name
                    }
                }
                ',
            ['name'=>$name, 'slug' => $slug, 'uuid' => $uuid, 'description'=> $description]
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
     * User can delete Category successfully test
     * @test
     * @return void
     */
    public function userCanDeleteCategorySuccessfullyTest()
    {
        $category = Category::inRandomOrder()->first();

        $uuid = $category->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation deleteCategory($uuid: ID!)
            {
                deleteCategory(uuid: $uuid )
                {
                    uuid
                    name
                }
            }
            ',
            ['uuid'=>$uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'deleteCategory' => [
                    'uuid',
                    'name',
                ],
            ],
        ]);
    }

    /**
     * User can not delete Category with invalid uuid test
     * @test
     * @return void
     */
    public function userCanNotDeleteCategoryWithInvalidUuidTest()
    {
        $category = Category::inRandomOrder()->first();

        $uuid = $this->faker->word.rand(0, 1000000);

        $response = $this->graphQL(/** @lang GraphQL */'
            mutation deleteCategory($uuid: ID!)
            {
                deleteCategory(uuid: $uuid )
                {
                    uuid
                    name
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
     * User can not delete Category that does not exist test
     * @test
     * @return void
     */
    public function userCanNotDeleteCategoryThatDoesExistTest()
    {
        $uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
                mutation deleteCategory($uuid: ID!)
                {
                    deleteCategory(uuid: $uuid )
                    {
                        uuid
                        name
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
     * User can not delete Category with empty uuid field test
     * @test
     * @return void
     */
    public function userCanNotDeleteCategoryWithEmptyUuidFieldTest()
    {
        $uuid = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                mutation deleteCategory($uuid: ID!)
                {
                    deleteCategory(uuid: $uuid )
                    {
                        uuid
                        name
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
     * User can get category successfully test
     * @test
     * @return void
     */
    public function userCanGetCategorySuccessfullyTest()
    {
        $category = Category::inRandomOrder()->first();
        $uuid = $category->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
                query getCategory($uuid: ID!)
                {
                    getCategory(uuid: $uuid )
                    {
                        uuid
                        name
                    }
                }
                ',
            ['uuid'=>$uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'getCategory' => [
                    'uuid',
                    'name',
                ],
            ],
        ]);
    }

    /**
     * User can not get category that does not exist test
     * @test
     * @return void
     */
    public function userCanNotGetCategoryThatDoesNotExistTest()
    {
        $uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
                   query getCategory($uuid: ID!)
                   {
                       getCategory(uuid: $uuid )
                       {
                           uuid
                           name
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
     * User can get category by Blog uuid successfully test
     * @test
     * @return void
     */
    public function userCanGetCategoriesByBlogUuidSuccessfullyTest()
    {
        $blog = Blog::inRandomOrder()->has('categories', '>', 1)->first();
        $blog_uuid = $blog->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
                query getCategoriesByBlogUuid($blog_uuid: ID!)
                {
                getCategoriesByBlogUuid(input: {blog_uuid: $blog_uuid })
                    {
                        data {
                            uuid
                            name
                        }
                    }
                }
                ',
            ['blog_uuid'=>$blog_uuid]
        );

        $response->assertJsonStructure([
            'data' => [
                'getCategoriesByBlogUuid' => [
                    'data' => [
                        [
                            'uuid',
                            'name',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * User cannot get category by Blog using invalid Blog uuid successfully test
     * @test
     * @return void
     */
    public function userCanNotGetCategoriesByBlogIdWithInvalidBlogUuidTest()
    {
        $blog_uuid = $this->faker->uuid;

        $response = $this->graphQL(/** @lang GraphQL */'
                   query categoriesByBlogUuid($blog_uuid: ID!)
                   {
                       categoriesByBlogUuid(input: {blog_uuid: $blog_uuid })
                       {
                           data {
                                uuid
                                name
                           }
                       }
                   }
                   ',
            ['blog_uuid'=>$blog_uuid]
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
     * User can get category by Blog using invalid Blog ID successfully test
     * @test
     * @return void
     */
    public function userCanNotGetCategoriesByBlogIdWithEmptyFieldsTest()
    {
        $blog_uuid = '';
        $blog_id = '';

        $response = $this->graphQL(/** @lang GraphQL */'
                   query categoriesByBlogUuid($blog_uuid: ID)
                   {
                       categoriesByBlogUuid(input: {blog_uuid: $blog_uuid })
                       {
                           data {
                                uuid
                                name
                           }
                       }
                   }
                   ',
            ['blog_uuid'=>$blog_uuid, 'blog_id'=>$blog_id]
        );

        $response->assertJsonStructure([
            'errors' => [
                [
                    'message',
                ],
            ],
        ]);
    }
}
