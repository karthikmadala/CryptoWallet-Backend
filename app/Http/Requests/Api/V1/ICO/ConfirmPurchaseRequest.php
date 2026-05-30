<?php

namespace App\Http\Requests\Api\V1\ICO;

use App\Enums\ChainType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ConfirmPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tx_hash'       => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'chain'         => ['required', 'string', new Enum(ChainType::class)],
            'from_address'  => ['required', 'string', 'max:255'],
            'token_amount'  => ['required', 'numeric', 'gt:0'],
            'eth_value'     => ['required', 'string', 'regex:/^\d+$/'],
            'payment_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
