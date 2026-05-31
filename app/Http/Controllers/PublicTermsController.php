<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PublicTermsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('public/Terms');
    }
}
