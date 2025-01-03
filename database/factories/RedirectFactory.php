<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedirectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Redirect::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'src_url' => $this->faker->url,
            'target' => $this->faker->url,
        ];
    }
}
