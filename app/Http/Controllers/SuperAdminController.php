<?php

namespace App\Http\Controllers;

use App\Mail\UserInvitationMail;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function __construct(private LicenseService $licenseService) {}

    public function dashboard()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $stats = [
            'groups'    => Group::count(),
            'companies' => Company::count(),
            'users'     => User::count(),
            'assets'    => Asset::count(),
        ];

        $groups = Group::withCount(['companies', 'users'])
            ->with('companies')
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                $group->asset_current = Asset::whereHas('company', fn ($q) => $q->where('group_id', $group->id))
                    ->where('status', 'active')
                    ->count();
                return $group;
            });

        return view('superadmin.dashboard', compact('stats', 'groups'));
    }

    public function groups()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $groups = Group::withCount(['companies', 'users'])
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                $group->asset_current = Asset::whereHas('company', fn ($q) => $q->where('group_id', $group->id))
                    ->where('status', 'active')
                    ->count();
                return $group;
            });

        return view('superadmin.groups', compact('groups'));
    }

    public function updateGroupLimit(Request $request, Group $group)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'asset_limit' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $group->update(['asset_limit' => $request->filled('asset_limit') ? (int) $request->asset_limit : null]);

        return back()->with('success', 'Límite del grupo "' . $group->name . '" actualizado.');
    }

    public function storeGroup(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:groups,name'],
        ]);

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Group::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        Group::create([
            'name'      => $request->name,
            'slug'      => $slug,
            'is_active' => true,
        ]);

        return redirect()
            ->route('superadmin.groups')
            ->with('success', 'Grupo creado correctamente.');
    }

    public function destroyGroup(Group $group)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($group->companies()->exists()) {
            return back()->with('error', 'No se puede eliminar el grupo porque tiene empresas asociadas.');
        }

        $group->delete();

        return redirect()
            ->route('superadmin.groups')
            ->with('success', 'Grupo eliminado correctamente.');
    }

    public function companies()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $companies = Company::with('group')
            ->withCount(['users', 'assets'])
            ->orderBy('name')
            ->get()
            ->map(function ($company) {
                $info = $this->licenseService->info($company);
                $company->license_info = $info;
                return $company;
            });

        $groups = Group::orderBy('name')->get();

        return view('superadmin.companies', compact('companies', 'groups'));
    }

    public function updateCompanyLimit(Request $request, Company $company)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'asset_limit' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $company->update(['asset_limit' => $request->filled('asset_limit') ? (int) $request->asset_limit : null]);

        return back()->with('success', 'Límite de "' . $company->name . '" actualizado.');
    }

    public function storeCompany(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'group_id' => ['required', 'exists:groups,id'],
        ]);

        Company::create([
            'name'              => $request->name,
            'group_id'          => $request->group_id,
            'show_in_processes' => $request->boolean('show_in_processes', true),
        ]);

        return redirect()
            ->route('superadmin.companies')
            ->with('success', 'Empresa creada correctamente.');
    }

    public function destroyCompany(Company $company)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($company->assets()->exists()) {
            return back()->with('error', 'No se puede eliminar la empresa porque tiene activos asociados.');
        }

        if ($company->users()->exists()) {
            return back()->with('error', 'No se puede eliminar la empresa porque tiene usuarios asociados.');
        }

        $company->delete();

        return redirect()
            ->route('superadmin.companies')
            ->with('success', 'Empresa eliminada correctamente.');
    }

    public function users()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $users = User::with(['role', 'company', 'group', 'jobPositions'])
            ->orderBy('name')
            ->paginate(25);

        $roles            = Role::orderBy('name')->get();
        $companies        = Company::orderBy('name')->get(['id', 'name', 'group_id']);
        $companiesByGroup = $companies->groupBy('group_id')->map->values();
        $groups           = Group::orderBy('name')->get();
        $jobPositions     = JobPosition::where('is_active', true)
            ->orderBy('group_id')
            ->orderBy('sort_order')
            ->get(['id', 'group_id', 'name']);

        return view('superadmin.users', compact('users', 'roles', 'companies', 'companiesByGroup', 'groups', 'jobPositions'));
    }

    public function storeUser(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id'       => ['required', 'exists:roles,id'],
            'company_id'    => ['nullable', 'exists:companies,id'],
            'group_id'      => ['nullable', 'exists:groups,id'],
            'module_access' => ['nullable', 'in:all,cumplimiento,procesos'],
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->slug === 'superadmin') {
            $scopeLevel   = 'global';
            $companyId    = null;
            $groupId      = null;
            $moduleAccess = 'all';
        } elseif ($role->slug === 'admin') {
            $scopeLevel   = 'group';
            $companyId    = $request->company_id;
            $groupId      = $request->group_id;
            $moduleAccess = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access : 'all';
        } else {
            $scopeLevel   = 'company';
            $companyId    = $request->company_id;
            $groupId      = $request->group_id;
            $moduleAccess = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access : 'all';
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
            'invited_by'        => auth()->id(),
        ]);

        $positionIds = array_filter((array) $request->input('job_position_ids', []));
        if (!empty($positionIds)) {
            $user->jobPositions()->attach($positionIds);
        }

        Mail::to($user->email)->send(new UserInvitationMail($user));

        return redirect()
            ->route('superadmin.users')
            ->with('success', 'Usuario invitado correctamente.');
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'role_id'       => ['required', 'exists:roles,id'],
            'company_id'    => ['nullable', 'exists:companies,id'],
            'group_id'      => ['nullable', 'exists:groups,id'],
            'module_access' => ['nullable', 'in:all,cumplimiento,procesos'],
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->slug === 'superadmin') {
            $scopeLevel   = 'global';
            $companyId    = null;
            $groupId      = null;
            $moduleAccess = 'all';
        } elseif ($role->slug === 'admin') {
            $scopeLevel   = 'group';
            $companyId    = $request->company_id;
            $groupId      = $request->group_id;
            $moduleAccess = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access : 'all';
        } else {
            $scopeLevel   = 'company';
            $companyId    = $request->company_id;
            $groupId      = $request->group_id;
            $moduleAccess = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access : 'all';
        }

        $user->update([
            'role_id'       => $role->id,
            'company_id'    => $companyId,
            'group_id'      => $groupId,
            'scope_level'   => $scopeLevel,
            'module_access' => $moduleAccess,
        ]);

        $positionIds = array_filter((array) $request->input('job_position_ids', []));
        $user->jobPositions()->sync($positionIds);

        return redirect()
            ->route('superadmin.users')
            ->with('success', "Usuario «{$user->name}» actualizado correctamente.");
    }

    public function destroyUser(User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return redirect()
            ->route('superadmin.users')
            ->with('success', 'Usuario eliminado correctamente.');
    }

}
