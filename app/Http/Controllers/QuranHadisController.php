<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class QuranHadisController extends Controller
{
    public function index()
    {
        return Inertia::render('QuranHadis/Index');
    }
}
