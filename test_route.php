<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/teacher-journals', 'GET');
$user = App\Models\User::first();
Auth::login($user);
$response = $kernel->handle($request);
echo "Status: " . $response->status() . "\n";
if ($response->status() != 200) {
    echo "Content: " . substr($response->getContent(), 0, 500) . "\n";
}
