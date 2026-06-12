<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Company;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)->orderBy('name')->get()
            : Company::where('id', $user->company_id)->get();

        $tokens = ApiToken::whereIn('company_id', $companies->pluck('id'))
            ->with('company')
            ->latest()
            ->get();

        return view('settings.api-tokens', compact('tokens', 'companies'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        abort_unless($user->canAccessCompany($company), 403);

        $raw    = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $raw);

        ApiToken::create([
            'company_id' => $company->id,
            'name'       => $data['name'],
            'token'      => $hashed,
        ]);

        return back()->with('generated_token', $raw)
                     ->with('success', 'Token generado. Cópialo ahora, no se mostrará de nuevo.');
    }

    public function destroy(ApiToken $apiToken)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->canAccessCompany($apiToken->company), 403);

        $apiToken->delete();

        return back()->with('success', 'Token revocado.');
    }
}
