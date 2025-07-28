<?php

namespace Homemove\AbTesting\Http\Controllers;

use Illuminate\Routing\Controller;

class TestController extends Controller
{
    public function index()
    {
        return view('ab-testing::test-page');
    }
}