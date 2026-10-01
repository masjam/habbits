<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\User::find(49);
foreach(['2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $d) { 
    $sch = $u->getEffectiveWorkSchedule($d); 
    echo $d . ': ' . $sch['work_start'] . "\n"; 
}
