<?php

namespace App\GraphQL\Validators;

use App\Exceptions\GqlException;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class CreateSubCategoryInputValidator extends Validator
{
    /**
     * @var \App\Models\Category;
     */
    protected $category;

    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $this->category = Category::firstWhere('uuid', $this->arg('category_uuid'));

        if (! $this->category) {
            throw new GqlException('category does not exist', 'category uuid is incorrect');
        }

        return [
            'category_uuid' => ['required', 'exists:categories,uuid', 'uuid'],
            'name' => ['required',
                Rule::unique('sub_categories', 'name')->where(function ($query) {
                    return $query->where('category_id', $this->category->id);
                }),
            ],
            'slug' => ['required',
                Rule::unique('sub_categories', 'slug')->where(function ($query) {
                    return $query->where('category_id', $this->category->id);
                }),
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A subcategory with this name already exists in this category',
            'slug.unique' => 'A subcategory with this slug already exists in this category',
        ];
    }
}
