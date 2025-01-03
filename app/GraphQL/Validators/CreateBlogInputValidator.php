<?php

namespace App\GraphQL\Validators;

use App\Models\Blog;
use Nuwave\Lighthouse\Validation\Validator;

class CreateBlogInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', function ($attribute, $name, $fail) {
                $blog = Blog::firstWhere('domain', $name);

                if ($blog) {
                    $fail('Domain exists already');
                }
            }],
        ];
    }
}
