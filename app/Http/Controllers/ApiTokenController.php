<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Group;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $groups  = Group::orderBy('name')->get();
        $tokens  = ApiToken::with('group')->latest()->get();

        return view('superadmin.api-tokens', compact('tokens', 'groups'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'group_id' => ['required', 'exists:groups,id'],
        ]);

        $raw    = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $raw);

        ApiToken::create([
            'group_id' => $data['group_id'],
            'name'     => $data['name'],
            'token'    => $hashed,
        ]);

        return back()
            ->with('generated_token', $raw)
            ->with('success', 'Token generado. Cópialo ahora, no se mostrará de nuevo.');
    }

    public function destroy(ApiToken $apiToken)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $apiToken->delete();

        return back()->with('success', 'Token revocado.');
    }
}
