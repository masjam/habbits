<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'nip' => $this->nip,
            'divisi' => $this->divisi,
            'avatar' => $this->avatar,
            'status_kehadiran' => $this->status_kehadiran,
            'current_streak' => (int) ($this->current_streak ?? 0),
            'longest_streak' => (int) ($this->longest_streak ?? 0),
            'personal_target' => $this->personal_target,
            'can_multi_login' => (bool) $this->can_multi_login,
            'work_start' => $this->work_start,
            'work_end' => $this->work_end,
            'late_tolerance' => $this->late_tolerance,
            'roles' => $this->relationLoaded('roles') ? $this->roles->pluck('name') : $this->getRoleNames(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
