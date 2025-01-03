<?php

namespace App\GraphQL\Validators;

use App\Exceptions\GqlException;
use App\Models\SubCategory;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class UpdateSubCategoryInputValidator extends Validator
{
    /**
     * @var \App\Models\Blog;
     */
    protected $category;

    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $subCategory = SubCategory::firstWhere('uuid', $this->arg('uuid'));

        if (! $subCategory) {
            throw new GqlException('Subcategory does not exist', 'Subcategory uuid is incorrect');
        }
        $this->category = $subCategory->category;

        return [
            'uuid' => ['required', 'exists:sub_categories,uuid', 'uuid'],
            'name' => [
                Rule::unique('sub_categories', 'name')->where(function ($query) {
                    return $query->where('category_id', $this->category->id);
                }),
            ],
            'slug' => [
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
