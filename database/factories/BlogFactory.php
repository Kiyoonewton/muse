<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Blog::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'uuid' => $this->faker->uuid,
            'site_uuid' => $this->faker->uuid,
            'domain' => $this->faker->word,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Blog $blog) {
            $facebookPage = FacebookPage::factory()->times(3)->create(['blog_id' => $blog->id]);
        });
    }
}
