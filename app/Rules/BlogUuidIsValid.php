<?php

namespace App\Rules;

use App\Models\Blog;
use Illuminate\Contracts\Validation\Rule;

class BlogUuidIsValid implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return ! is_null(Blog::where('uuid', $value)->first());
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The Blog Uuid is invalid.';
    }
}
