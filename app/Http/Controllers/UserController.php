<?php

namespace App\Http\Controllers;

use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        $users = User::with(['role', 'company', 'group'])
            ->when($authUser->hasGroupScope(), function ($query) use ($authUser) {
                $query->where('group_id', $authUser->group_id);
            }, function ($query) use ($authUser) {
                $query->where('company_id', $authUser->company_id);
            })
            ->latest()
            ->paginate(10);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        $allowedRoleSlugs = ['admin', 'operative', 'readonly'];

        // Company-scope admins cannot assign the admin role (group-wide)
        if (! $authUser->hasGroupScope()) {
            $allowedRoleSlugs = ['operative', 'readonly'];
        }

        $roles = Role::whereIn('slug', $allowedRoleSlugs)->orderBy('name')->get();

        $companies = Company::query()
            ->when($authUser->hasGroupScope(), function ($query) use ($authUser) {
                $query->where('group_id', $authUser->group_id);
            }, function ($query) use ($authUser) {
                $query->where('id', $authUser->company_id);
            })
            ->orderBy('name')
            ->get();

        $singleCompany = $companies->count() === 1 ? $companies->first() : null;

        return view('users.create', compact('roles', 'companies', 'singleCompany'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::findOrFail($request->company_id);

        if (! $authUser->canAccessCompany($company)) {
            abort(403);
        }

        $role = Role::findOrFail($request->role_id);

        // Block assigning roles above the creator's own role
        abort_if($role->slug === 'superadmin', 403);

        // An admin with group scope can create group-scope admins.
        // An admin with company scope can only create company-scope users.
        $scopeLevel = ($role->slug === 'admin' && $authUser->hasGroupScope()) ? 'group' : 'company';

        $user = User::create([
            'name'                   => $request->name,
            'email'                  => $request->email,
            'role_id'                => $role->id,
            'company_id'             => $company->id,
            'group_id'               => $company->group_id,
            'scope_level'            => $scopeLevel,
            'password'               => null,
            'status'                 => 'invited',
            'invite_token'           => Str::random(64),
            'invite_expires_at'      => now()->addDays(3),
            'invited_by'             => $authUser->id,
        ]);

        Mail::to($user->email)->send(new UserInvitationMail($user));

        return redirect()
            ->route('users.index')
            ->with('success', 'Invitación enviada correctamente.');
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        if (! $user->company || ! $authUser->canAccessCompany($user->company)) {
            abort(403);
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }
}