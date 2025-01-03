<?php

declare(strict_types=1);

namespace App\GraphQL\Validators;

use App\Exceptions\GqlException;
use Nuwave\Lighthouse\Validation\Validator;

final class GetBlogInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => [function ($attribute, $filter, $fail) {
                $columns = ['uuid', 'site_uuid', 'domain'];
                if (! in_array($filter['column'], $columns)) {
                    $fail('The specified filter column is not supported');
                }
            }],
        ];
    }
}
