<?php

namespace App\Http\Requests;

use App\Models\PerformanceAssignment;
use App\Models\User;
use App\Policies\PerformanceAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && app(PerformanceAssignmentPolicy::class)->create($user);
    }

    public function rules(): array
    {
        return [
            'academic_year' => ['required', 'string', 'max:20'],
            'period_type'   => ['required', 'in:bulanan,semester_1,semester_2'],
            'period_label'  => ['required', 'string', 'max:32'],
            'assessor_id'   => ['required', 'integer', 'exists:users,id'],
            'employee_id'   => ['required', 'integer', 'exists:users,id', 'different:assessor_id'],
            'note'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = User::find($this->input('employee_id'));

            if ($employee && $employee->hasRole('kepala_sekolah')) {
                $validator->errors()->add('employee_id', 'Kepala Sekolah tidak dinilai melalui delegasi KPI.');
            }

            $exists = PerformanceAssignment::active()
                ->where('employee_id', $this->input('employee_id'))
                ->forPeriod(
                    (string) $this->input('academic_year'),
                    (string) $this->input('period_type'),
                    (string) $this->input('period_label')
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('employee_id', 'Pegawai ini sudah memiliki penilai delegasi aktif pada periode tersebut.');
            }
        });
    }
}
