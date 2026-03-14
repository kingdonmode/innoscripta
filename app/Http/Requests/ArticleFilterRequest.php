<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'        => ['nullable', 'string', 'max:255'],
            'source'   => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
            'author'   => ['nullable', 'string'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'sort'     => ['nullable', 'string', 'in:published_at,title'],
            'order'    => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
