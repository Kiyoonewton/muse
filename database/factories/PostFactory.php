<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->randomHtml(5, 6),
            'slug' => $this->faker->slug(),
            'category_id' => Category::inRandomOrder()->first(),
            'author_uuid' => $this->faker->uuid,
            'status' => true,
            'language_attr' => $this->faker->locale,
            'blog_id' => Blog::inRandomOrder()->first(),
        ];
    }
}
