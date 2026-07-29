<?php

namespace App\Http\Requests\Concerns;

trait ValidatesTemplateDesign
{
    protected function templateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'width' => ['required', 'numeric', 'gt:0'],
            'height' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'in:mm,cm,in,px'],
            'has_back' => ['sometimes', 'boolean'],

            'front_design' => ['required', 'array'],
            'front_design.background' => ['required', 'array'],
            'front_design.background.type' => ['required', 'string', 'in:color,image'],
            'front_design.background.value' => ['required', 'string'],
            'front_design.elements' => ['present', 'array'],

            'back_design' => ['nullable', 'array'],
            ...$this->backDesignRules(),

            ...$this->elementRules('front_design.elements'),
            ...$this->elementRules('back_design.elements'),
        ];
    }

    private function backDesignRules(): array
    {
        if ($this->input('back_design') === null) {
            return [];
        }

        return [
            'back_design.background' => ['required', 'array'],
            'back_design.background.type' => ['required', 'string', 'in:color,image'],
            'back_design.background.value' => ['required', 'string'],
            'back_design.elements' => ['present', 'array'],
        ];
    }

    private function elementRules(string $prefix): array
    {
        return [
            "{$prefix}.*.id" => ['required', 'string'],
            "{$prefix}.*.type" => ['required', 'string', 'in:text,dynamic_text,image,photo,signature,qrcode,barcode,shape'],
            "{$prefix}.*.x" => ['required', 'numeric'],
            "{$prefix}.*.y" => ['required', 'numeric'],
            "{$prefix}.*.width" => ['required', 'numeric', 'gt:0'],
            "{$prefix}.*.height" => ['required', 'numeric', 'gt:0'],
            "{$prefix}.*.rotation" => ['required', 'numeric'],
            "{$prefix}.*.opacity" => ['required', 'numeric', 'between:0,1'],

            "{$prefix}.*.content" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.field" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.source" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.fontFamily" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.fontSize" => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            "{$prefix}.*.fontWeight" => ['sometimes', 'nullable', 'string', 'in:normal,bold'],
            "{$prefix}.*.fontStyle" => ['sometimes', 'nullable', 'string', 'in:normal,italic'],
            "{$prefix}.*.color" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.textAlign" => ['sometimes', 'nullable', 'string', 'in:left,center,right'],
            "{$prefix}.*.lineHeight" => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            "{$prefix}.*.letterSpacing" => ['sometimes', 'nullable', 'numeric'],

            "{$prefix}.*.value" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.format" => ['sometimes', 'nullable', 'string', 'in:CODE128,CODE39,EAN13,EAN8,UPC'],
            "{$prefix}.*.foreground" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.background" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.shape" => ['sometimes', 'nullable', 'string', 'in:rectangle,circle,line'],
            "{$prefix}.*.fill" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.stroke" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.strokeWidth" => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
