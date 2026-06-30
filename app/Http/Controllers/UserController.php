<?php

namespace App\Http\Controllers;

use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
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

        $users = User::with(['role', 'company', 'group', 'jobPositions'])
            ->when($authUser->isGlobalScope(), function ($query) {
                // global-scope admins see all users except superadmins
                $query->whereHas('role', fn ($q) => $q->where('slug', '!=', 'superadmin'));
            }, function ($query) use ($authUser) {
                if ($authUser->hasGroupScope()) {
                    $query->where('group_id', $authUser->group_id);
                } else {
                    $query->where('company_id', $authUser->company_id);
                }
            })
            ->latest()
            ->paginate(10);

        $allowedRoleSlugs = ($authUser->hasGroupScope() || $authUser->isGlobalScope())
            ? ['admin', 'operative', 'readonly']
            : ['operative', 'readonly'];

        $roles = Role::whereIn('slug', $allowedRoleSlugs)->orderBy('name')->get();

        $adminRoleId = $roles->where('slug', 'admin')->first()?->id;

        $positionsByGroup = JobPosition::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'group_id', 'name'])
            ->groupBy('group_id')
            ->map->values();

        return view('users.index', compact('users', 'roles', 'adminRoleId', 'positionsByGroup'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        $canAssignAdmin = $authUser->hasGroupScope() || $authUser->isGlobalScope();
        $allowedRoleSlugs = $canAssignAdmin ? ['admin', 'operative', 'readonly'] : ['operative', 'readonly'];

        $roles = Role::whereIn('slug', $allowedRoleSlugs)->orderBy('name')->get();

        $groups = Group::query()
            ->when($authUser->hasGroupScope(), fn ($q) => $q->where('id', $authUser->group_id))
            ->orderBy('name')
            ->get();

        $companies = Company::query()
            ->when($authUser->hasGroupScope(), fn ($q) => $q->where('group_id', $authUser->group_id))
            ->when(! $authUser->hasGroupScope() && ! $authUser->isGlobalScope(),
                fn ($q) => $q->where('id', $authUser->company_id))
            ->orderBy('name')
            ->get();

        $singleCompany = (! $authUser->hasGroupScope() && ! $authUser->isGlobalScope() && $companies->count() === 1)
            ? $companies->first()
            : null;

        $positionsByGroup = JobPosition::query()
            ->when($authUser->hasGroupScope(), fn ($q) => $q->where('group_id', $authUser->group_id))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group_id')
            ->map->values();

        return view('users.create', compact('roles', 'groups', 'companies', 'singleCompany', 'positionsByGroup'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id'  => ['required', 'exists:roles,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
        ]);

        $role = Role::findOrFail($request->role_id);

        abort_if($role->slug === 'superadmin', 403);
        abort_if($role->slug === 'admin' && ! $authUser->hasGroupScope() && ! $authUser->isGlobalScope(), 403);

        if ($role->slug === 'admin') {
            $groupId      = $request->group_id ?? $authUser->group_id;
            $companyId    = null;
            $scopeLevel   = 'group';
            $moduleAccess = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access
                : 'all';
        } else {
            $request->validate([
                'company_id'    => ['nullable', 'exists:companies,id'],
                'module_access' => ['required', 'in:all,cumplimiento,procesos'],
            ]);
            $moduleAccess = $request->module_access;

            if ($request->filled('company_id')) {
                $company = Company::findOrFail($request->company_id);
                if (! $authUser->isGlobalScope() && ! $authUser->canAccessCompany($company)) {
                    abort(403);
                }
                $companyId  = $company->id;
                $groupId    = $request->group_id ?? $company->group_id;
                $scopeLevel = 'company';
            } else {
                $companyId  = null;
                $groupId    = $request->group_id ?? $authUser->group_id;
                $scopeLevel = 'group';
            }
        }

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'role_id'           => $role->id,
            'company_id'        => $companyId,
            'group_id'          => $groupId,
            'scope_level'       => $scopeLevel,
            'module_access'     => $moduleAccess,
            'password'          => null,
            'status'            => 'invited',
            'invite_token'      => Str::random(64),
            'invite_expires_at' => now()->addDays(3),
            'invited_by'        => $authUser->id,
        ]);

        if ($request->filled('job_position_id')) {
            $user->jobPositions()->attach($request->job_position_id);
        }

        Mail::to($user->email)->send(new UserInvitationMail($user));

        return redirect()
            ->route('users.index')
            ->with('success', 'Invitación enviada correctamente.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        if (! $authUser->isGlobalScope() && ! $authUser->canAccessCompany($user->company)) {
            abort(403);
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'No puedes cambiar tu propio rol.');
        }

        $request->validate([
            'role_id'       => ['required', 'exists:roles,id'],
            'module_access' => ['nullable', 'in:all,cumplimiento,procesos'],
        ]);

        $role = Role::findOrFail($request->role_id);

        abort_if($role->slug === 'superadmin', 403);
        abort_if($role->slug === 'admin' && ! $authUser->hasGroupScope() && ! $authUser->isGlobalScope(), 403);

        $scopeLevel   = ($role->slug === 'admin') ? 'group' : ($user->company_id ? 'company' : 'group');
        $moduleAccess = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
            ? $request->module_access
            : 'all';

        $user->update([
            'role_id'       => $role->id,
            'scope_level'   => $scopeLevel,
            'module_access' => $moduleAccess,
        ]);

        if ($request->filled('job_position_id')) {
            $user->jobPositions()->sync([$request->job_position_id]);
        } else {
            $user->jobPositions()->detach();
        }

        return redirect()
            ->route('users.index')
            ->with('success', "Usuario «{$user->name}» actualizado correctamente.");
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        if (! $authUser->isGlobalScope() && (! $user->company || ! $authUser->canAccessCompany($user->company))) {
            abort(403);
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($user->isAdmin() && ! $authUser->isSuperAdmin()) {
            return back()->with('error', 'Solo el superadministrador puede eliminar administradores.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }
}