<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseApiRequest extends FormRequest
{
    /**
     * Handle failed validation for API requests.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error(
                message: 'Validation error',
                statusCode: 422,
                errors: $this->formatErrors($validator)
            )

        );
    }

    /**
     * Format validation errors in a clean structured format
     * (Recommended for frontend & mobile apps)
     */
    private function formatErrors(Validator $validator)
    {
        return collect($validator->errors()->toArray())
            ->flatMap(function ($messages, $field) {
                return collect($messages)->map(function ($message) use ($field) {
                    return [
                        'field' => $field,
                        'message' => $message,
                    ];
                });
            })
            ->values();
    }
}
