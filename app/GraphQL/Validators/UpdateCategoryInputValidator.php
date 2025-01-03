<?php

namespace App\GraphQL\Validators;

use App\Exceptions\GqlException;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class UpdateCategoryInputValidator extends Validator
{
    /**
     * @var \App\Models\Blog;
     */
    protected $blog;

    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $category = Category::firstWhere('uuid', $this->arg('uuid'));

        if (! $category) {
            throw new GqlException('Category does not exist', 'Category uuid is incorrect');
        }
        $this->blog = $category->blog;

        return [
            'uuid' => ['required', 'exists:categories,uuid', 'uuid'],
            'name' => [
                Rule::unique('categories', 'name')->where(function ($query) use ($category) {
                    return $query->where('blog_id', $category->blog_id);
                })->ignore($category->id),
            ],
            'slug' => [
                Rule::unique('categories', 'slug')->where(function ($query) use ($category) {
                    return $query->where('blog_id', $category->blog_id);
                })->ignore($category->id),
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists',
            'slug.unique' => 'A category with this slug already exists',
        ];
    }
}
