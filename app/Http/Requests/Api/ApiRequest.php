<?php

namespace App\Http\Requests\Api;

use App\Support\ValidationMessage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Every API request validates through here so failures always come back
 * in the same envelope the success responses use, with every message in
 * `message` (comma separated) and no per-field `errors` object.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => ValidationMessage::from($validator->errors()->toArray()),
        ], 422));
    }
}
