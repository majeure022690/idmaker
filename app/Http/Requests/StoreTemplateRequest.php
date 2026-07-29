<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTemplateDesign;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    use ValidatesTemplateDesign;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->templateRules();
    }
}
