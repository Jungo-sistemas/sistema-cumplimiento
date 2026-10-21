<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProcessesGuideController extends Controller
{
    public function complete(Request $request)
    {
        $user = $request->user();

        if (is_null($user->procesos_guide_completed_at)) {
            $user->update(['procesos_guide_completed_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function manual()
    {
        return view('processes.guide-manual');
    }
}
