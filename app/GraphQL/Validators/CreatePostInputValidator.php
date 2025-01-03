<?php

namespace App\GraphQL\Validators;

use App\Models\Blog;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class CreatePostInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'boolean'],
            'content' => ['required_if:status,true'],
            'title' => ['required_if:status,true'],
            'author_uuid' => ['required'],
            'category_uuids' => ['exists:categories,uuid'],
            'blog_uuid' => ['required', 'exists:blogs,uuid'],
            'slug' => [
                'required_if:status,true',
                Rule::unique('posts', 'slug')->where(function ($query) {
                    $blog = Blog::firstWhere('uuid', $this->arg('blog_uuid'));

                    return $query->where('blog_id', $blog->id);
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
            'slug.unique' => 'This user already has a post with this slug',
            'content.required_if' => 'Content is required in order to publish the post',
            'title.required_if' => 'Title is required in order to publish the post',
            'slug.required_if' => 'Slug is required in order to publish the post',
        ];
    }
}
