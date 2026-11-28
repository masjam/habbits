<?php

namespace App\Http\Requests;

use App\Services\PerformanceScoreCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class SaveAssignedEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'          => ['required', 'integer', 'exists:users,id'],
            'academic_year'    => ['required', 'string', 'max:20'],
            'period_type'      => ['required', 'in:bulanan,semester_1,semester_2'],
            'period_label'     => ['required', 'string', 'max:32'],
            'category'         => ['required', 'in:guru,karyawan'],
            'indicator_scores' => ['required', 'array'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $requireComplete = $this->routeIs('performance.assigned.submit');
            $category = (string) $this->input('category');
            $scores = (array) $this->input('indicator_scores', []);

            try {
                app(PerformanceScoreCalculator::class)->calculate($category, $scores, $requireComplete);
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('indicator_scores', $e->getMessage());
            }
        });
    }
}
