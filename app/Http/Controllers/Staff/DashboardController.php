<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $khsCount = Krs::query()->where('status', 'approved')->count();

        return view('staff.dashboard', compact('khsCount'));
    }
}
