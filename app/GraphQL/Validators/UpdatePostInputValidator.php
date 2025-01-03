<?php

namespace App\GraphQL\Validators;

use App\Exceptions\GqlException;
use App\Models\Post;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class UpdatePostInputValidator extends Validator
{
    /**
     * @var App\Models\Post;
     */
    protected $post;

    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $this->post = Post::firstWhere('uuid', $this->arg('uuid'));

        if (! $this->post) {
            throw new GqlException('Post does not exist', 'incorrect post uuid');
        }

        return [
            'uuid' => ['required', 'exists:posts,uuid'],
            'status' => ['boolean'],
            'content' => ['required_if:status,true'],
            'title' => ['required_if:status,true'],
            'category_uuids' => ['exists:categories,uuid'],
            'slug' => [
                'required_if:status,true',
                Rule::unique('posts', 'slug')->where(function ($query) {
                    $query->where('blog_id', $this->post->blog->id);
                })->ignore($this->post),
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
