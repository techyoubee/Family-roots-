<?php

namespace App\Controllers;

use App\Core\Request;

final class PrivacyController extends Controller
{
    public function index(Request $request)
    {
        return $this->view('privacy/index', [
            'title' => 'Privacy policy',
        ]);
    }
}
