<?php

namespace App\Http\Controllers;

use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Http\Request;

class JobPositionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() && $user->hasGroupScope(), 403);

        $positions = JobPosition::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with('users')
            ->get();

        // Usuarios activos del grupo para el selector
        $groupUsers = User::where('group_id', $user->group_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('job-positions.index', compact('positions', 'groupUsers'));
    }

    public function assignUser(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() && $user->hasGroupScope(), 403);

        $request->validate([
            'job_position_id' => 'required|exists:job_positions,id',
            'user_id'         => 'required|exists:users,id',
        ]);

        $position = JobPosition::findOrFail($request->job_position_id);
        abort_unless($position->group_id === $user->group_id, 403);

        $position->users()->syncWithoutDetaching([$request->user_id]);

        return back()->with('success', 'Usuario asignado correctamente.');
    }

    public function removeUser(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() && $user->hasGroupScope(), 403);

        $request->validate([
            'job_position_id' => 'required|exists:job_positions,id',
            'user_id'         => 'required|exists:users,id',
        ]);

        $position = JobPosition::findOrFail($request->job_position_id);
        abort_unless($position->group_id === $user->group_id, 403);

        $position->users()->detach($request->user_id);

        return back()->with('success', 'Usuario removido del puesto.');
    }
}
