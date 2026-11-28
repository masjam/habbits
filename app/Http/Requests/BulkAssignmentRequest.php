<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Policies\PerformanceAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && app(PerformanceAssignmentPolicy::class)->create($user);
    }

    public function rules(): array
    {
        return [
            'academic_year'  => ['required', 'string', 'max:20'],
            'period_type'    => ['required', 'in:bulanan,semester_1,semester_2'],
            'period_label'   => ['required', 'string', 'max:32'],
            'assessor_id'    => ['required', 'integer', 'exists:users,id'],
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'exists:users,id'],
            'note'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employeeIds = collect($this->input('employee_ids', []))->map(fn ($id) => (int) $id);
            if ($employeeIds->contains((int) $this->input('assessor_id'))) {
                $validator->errors()->add('employee_ids', 'Penilai tidak dapat ditugaskan untuk menilai dirinya sendiri.');
            }
        });
    }
}
