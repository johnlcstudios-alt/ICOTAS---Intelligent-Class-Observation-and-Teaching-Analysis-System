<?php

namespace App\Controllers;

use App\Core\Auth;

final class HomeController extends BaseController
{
    public function index(): void
    {
        if (Auth::check()) {
            redirect(Auth::dashboardPath());
        }
        redirect('login');
    }
}
