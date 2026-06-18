<?php

namespace App\Http\Controllers;

use App\Models\RegulationApproval;

class MyApprovalsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $pendingApprovals = RegulationApproval::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with([
                'regulation.company',
                'regulation.processType',
                'regulation.creator',
                'regulation.currentVersion',
                'jobPosition',
            ])
            ->orderBy('created_at')
            ->get();

        return view('my-approvals.index', compact('pendingApprovals'));
    }
}
