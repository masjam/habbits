<?php

namespace App\Services\Dashboard;

use App\Models\User;

interface DashboardBuilderInterface
{
    public function roleKey(): string;

    public function build(User $user, DashboardContext $ctx): array;

    public function buildDeferred(User $user, DashboardContext $ctx): array;
}
