<?php

namespace App\Http\Controllers\OrganizationOfficer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the Organization Officer dashboard.
     */
    public function index()
    {
        return view('organization-officer.dashboard');
    }
}
