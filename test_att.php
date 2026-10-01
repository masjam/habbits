<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$atts = App\Models\Attendance::where('user_id', 49)->where('date', 'like', '2026-09-%')->get();
foreach($atts as $a) {
    echo $a->date . ' | ' . $a->time_in . ' | ' . $a->status . "\n";
}
