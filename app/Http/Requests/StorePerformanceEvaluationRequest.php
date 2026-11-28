<?php

namespace App\Http\Requests;

use App\Policies\PerformanceEvaluationPolicy;
use App\Services\PerformanceScoreCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StorePerformanceEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && app(PerformanceEvaluationPolicy::class)->isApprover($user);
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
            'status'           => ['required', 'in:draft,published'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $category = (string) $this->input('category');
            $scores = (array) $this->input('indicator_scores', []);
            $requireComplete = $this->input('status') === 'published';

            try {
                app(PerformanceScoreCalculator::class)->calculate($category, $scores, $requireComplete);
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('indicator_scores', $e->getMessage());
            }
        });
    }
}
