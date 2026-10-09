<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;

use Inertia\Inertia;

class QuranHadisController extends Controller
{
    public function index()
    {
        return Inertia::render('QuranHadis/Index');
    }
}
