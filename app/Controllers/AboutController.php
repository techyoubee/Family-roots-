<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;

final class AboutController extends Controller
{
    public function index(Request $request)
    {
        $matching = Config::get('matching', []);

        return $this->view('about/index', [
            'title'    => 'About Family Roots',
            'matching' => $matching,
        ]);
    }
}
