<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use Plugin\PluginForgeTools\Services\UrlSecurityValidator;

class AnalyzeUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => trans('PluginForgeTools::common.validation.url_required'),
            'url.string'   => trans('PluginForgeTools::common.validation.url_invalid'),
            'url.max'      => trans('PluginForgeTools::common.validation.url_too_long'),
            'url.url'      => trans('PluginForgeTools::common.validation.url_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $url = (string) $this->input('url', '');
            try {
                app(UrlSecurityValidator::class)->validate($url);
            } catch (InvalidArgumentException $e) {
                $key        = $e->getMessage() ?: 'url_invalid';
                $transKey   = "PluginForgeTools::common.validation.$key";
                $translated = trans($transKey);
                if ($translated === $transKey) {
                    $translated = trans('PluginForgeTools::common.validation.url_invalid');
                }
                $validator->errors()->add('url', $translated);
            }
        });
    }
}
