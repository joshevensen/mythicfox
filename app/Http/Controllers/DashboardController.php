<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            $firstName = preg_split('/\s+/', $name)[0];
        } else {
            $email = (string) ($user->email ?? '');
            $firstName = $email !== '' ? strtok($email, '@') : '';
        }

        return Inertia::render('Dashboard', [
            'firstName' => $firstName,
        ]);
    }
}
