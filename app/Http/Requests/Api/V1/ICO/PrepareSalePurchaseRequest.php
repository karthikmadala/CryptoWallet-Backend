<?php

namespace App\Http\Requests\Api\V1\ICO;

use App\Enums\ChainType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PrepareSalePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'uuid'],
            'wallet_address'    => ['required', 'string', 'max:42'],
            'token_amount'      => ['required', 'numeric', 'gt:0'],
            'payment_amount'    => ['required', 'regex:/^\d+$/'],
        ];
    }
}
