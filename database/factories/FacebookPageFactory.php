<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacebookPageFactory extends Factory
{
    protected $model = FacebookPage::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'page_id' => rand(20, 700),
            'name' => $this->faker->word,
        ];
    }
}
