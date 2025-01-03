<?php

namespace App\GraphQL\Validators;

use App\Exceptions\GqlException;
use App\Models\Blog;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class CreateCategoryInputValidator extends Validator
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
        $this->blog = Blog::firstWhere('uuid', $this->arg('blog_uuid'));

        if (! $this->blog) {
            throw new GqlException('blog does not exist', 'blog uuid is incorrect');
        }

        return [
            'blog_uuid' => ['required', 'exists:blogs,uuid', 'uuid'],
            'name' => ['required',
                Rule::unique('categories', 'name')->where(function ($query) {
                    return $query->where('blog_id', $this->blog->id);
                }),
            ],
            'slug' => ['required',
                Rule::unique('categories', 'slug')->where(function ($query) {
                    return $query->where('blog_id', $this->blog->id);
                }),
            ],
            'parent_uuid' => ['exists:categories,uuid'],
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
