<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Product\Models\SizeChartRow;

class SizeRecommendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        // Each known measurement is optional but, if present, numeric & sane.
        foreach (SizeChartRow::MEASUREMENTS as $key) {
            $rules[$key] = ['nullable', 'numeric', 'min:1', 'max:300'];
        }

        return $rules;
    }

    /**
     * Only the measurement keys, dropping nulls.
     *
     * @return array<string, float>
     */
    public function measurements(): array
    {
        return collect($this->validated())
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
