<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DzikirController extends Controller
{
    /**
     * Tampilkan halaman Dzikir Pagi & Petang.
     */
    public function index(): Response
    {
        return Inertia::render('Dzikir/Index');
    }
}
