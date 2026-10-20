<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AccessControlValidationRules
{
    /**
     * Lowercase letters, digits, dots, underscores and dashes (e.g. "post.edit", "super-admin").
     */
    public const string ACCESS_NAME_PATTERN = '/^[a-z0-9._-]+$/';

    /**
     * Get the validation rules for a permission or role name.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function accessNameRules(string $table, int|string|null $ignoreId = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            'regex:'.self::ACCESS_NAME_PATTERN,
            Rule::unique($table, 'name')->ignore($ignoreId),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function accessNameMessages(string $label): array
    {
        return [
            'name.regex' => 'The :attribute may only contain lowercase letters, numbers, dots, underscores and dashes.',
            'name.unique' => "This {$label} name is already in use.",
        ];
    }
}
