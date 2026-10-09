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

        $users = User::with(['role', 'company', 'group', 'jobPositions', 'companies'])
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
            ? ['admin', 'operative', 'readonly', 'auditor']
            : ['operative', 'readonly', 'auditor'];

        $roles = Role::whereIn('slug', $allowedRoleSlugs)->orderBy('name')->get();

        $adminRoleId = $roles->where('slug', 'admin')->first()?->id;

        $positionsByGroup = JobPosition::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'group_id', 'name'])
            ->groupBy('group_id')
            ->map->values();

        // Mismo criterio de alcance que create(): un admin de grupo ve todas las empresas de su
        // grupo, uno de empresa única solo la suya — agrupado por group_id igual que
        // positionsByGroup, para que el modal de edición pueda reaccionar al grupo del usuario
        // que se esté editando en cada momento (un admin con alcance global puede estar editando
        // usuarios de distintos grupos en la misma tabla).
        $companiesByGroup = Company::query()
            ->when($authUser->hasGroupScope(), fn ($q) => $q->where('group_id', $authUser->group_id))
            ->when(! $authUser->hasGroupScope() && ! $authUser->isGlobalScope(),
                fn ($q) => $q->where('id', $authUser->company_id))
            ->orderBy('name')
            ->get(['id', 'group_id', 'name'])
            ->groupBy('group_id')
            ->map->values();

        return view('users.index', compact('users', 'roles', 'adminRoleId', 'positionsByGroup', 'companiesByGroup'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        $canAssignAdmin = $authUser->hasGroupScope() || $authUser->isGlobalScope();
        $allowedRoleSlugs = $canAssignAdmin
            ? ['admin', 'operative', 'readonly', 'auditor']
            : ['operative', 'readonly', 'auditor'];

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
            $groupId         = $request->group_id ?? $authUser->group_id;
            $companyId       = null;
            $scopeLevel      = 'group';
            $companiesToSync = [];
            $moduleAccess    = in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access
                : 'all';
        } else {
            $request->validate([
                'company_id'    => ['nullable', 'array'],
                'company_id.*'  => ['exists:companies,id'],
                'module_access' => ['required', 'in:all,cumplimiento,procesos'],
            ]);
            $moduleAccess = $role->slug === 'auditor' ? 'procesos' : $request->module_access;

            [$companyId, $groupId, $scopeLevel, $companiesToSync] = $this->resolveCompanyScope(
                $authUser,
                $request->input('company_id', []),
                $request->group_id ?? $authUser->group_id,
            );
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

        if (! empty($companiesToSync)) {
            $user->companies()->sync($companiesToSync);
        }

        if ($request->filled('job_position_id')) {
            $user->jobPositions()->attach($request->job_position_id);
        }

        Mail::to($user->email)->send(new UserInvitationMail($user));

        return redirect()
            ->route('users.index')
            ->with('success', 'Invitación enviada correctamente.');
    }

    /**
     * Resuelve company_id/group_id/scope_level a partir de los ids de empresa seleccionados en
     * el formulario (checkboxes) — sin depender de rol ni puesto: 0 seleccionadas = alcance de
     * grupo, 1 = alcance de empresa (como siempre), 2+ = alcance de "varias empresas"
     * (scope_level 'companies', sincronizadas en user_companies). Usado por store() y update().
     *
     * @param  array<int, mixed>  $companyIds
     * @return array{0: ?int, 1: ?int, 2: string, 3: array<int, int>} [companyId, groupId, scopeLevel, companiesToSync]
     */
    private function resolveCompanyScope(User $authUser, array $companyIds, ?int $fallbackGroupId): array
    {
        $ids = collect($companyIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [null, $fallbackGroupId, 'group', []];
        }

        $companies = Company::whereIn('id', $ids)->get();

        abort_if($companies->count() !== $ids->count(), 422, 'Una o más empresas seleccionadas no existen.');

        foreach ($companies as $company) {
            if (! $authUser->isGlobalScope() && ! $authUser->canAccessCompany($company)) {
                abort(403);
            }
        }

        if ($companies->count() === 1) {
            $company = $companies->first();

            return [$company->id, $company->group_id, 'company', []];
        }

        // Confirmado con negocio: un usuario con varias empresas siempre las tiene dentro del
        // mismo grupo (nunca mezcladas entre distintos clientes/grupos).
        abort_if($companies->pluck('group_id')->unique()->count() > 1, 422, 'Las empresas seleccionadas deben pertenecer al mismo grupo.');

        return [null, $companies->first()->group_id, 'companies', $ids->all()];
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        // Un usuario sin empresa (alcance de grupo) no tiene "empresa" que comparar —
        // canAccessCompany(null) siempre da false, así que en ese caso el acceso se valida
        // por grupo en vez de por empresa (si no, ningún admin de grupo podría volver a
        // editar a alguien al que le quitó la empresa).
        $canManageTarget = $user->company
            ? $authUser->canAccessCompany($user->company)
            : $authUser->canAccessGroup($user->group);

        if (! $authUser->isGlobalScope() && ! $canManageTarget) {
            abort(403);
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'No puedes cambiar tu propio rol.');
        }

        // Mismo criterio que destroy(): un admin no puede tocar a otro admin (ni al
        // superadmin) — solo el superadministrador puede editar administradores.
        if ($user->isAdmin() && ! $authUser->isSuperAdmin()) {
            return back()->with('error', 'Solo el superadministrador puede editar a otros administradores.');
        }

        $request->validate([
            'role_id'          => ['required', 'exists:roles,id'],
            'company_id'       => ['nullable', 'array'],
            'company_id.*'     => ['exists:companies,id'],
            'module_access'    => ['nullable', 'in:all,cumplimiento,procesos'],
            'job_position_id'  => ['nullable', 'array'],
            'job_position_id.*' => ['exists:job_positions,id'],
        ]);

        $role = Role::findOrFail($request->role_id);

        abort_if($role->slug === 'superadmin', 403);
        abort_if($role->slug === 'admin' && ! $authUser->hasGroupScope() && ! $authUser->isGlobalScope(), 403);

        // Mismo criterio que store(): un admin no pertenece a una empresa en particular (vive a
        // nivel de grupo); cualquier otro rol sí puede tener una o varias empresas (ver
        // resolveCompanyScope()), y si se le asigna alguna distinta a la que ya tenía, el grupo
        // se recalcula a partir de esa empresa (no se deja elegir grupo aparte en este modal).
        if ($role->slug === 'admin') {
            $companyId       = null;
            $groupId         = $user->group_id;
            $scopeLevel      = 'group';
            $companiesToSync = [];
        } else {
            [$companyId, $groupId, $scopeLevel, $companiesToSync] = $this->resolveCompanyScope(
                $authUser,
                $request->input('company_id', []),
                $user->group_id,
            );
        }

        $moduleAccess = $role->slug === 'auditor'
            ? 'procesos'
            : (in_array($request->module_access, ['all', 'cumplimiento', 'procesos'])
                ? $request->module_access
                : 'all');

        $user->update([
            'role_id'       => $role->id,
            'company_id'    => $companyId,
            'group_id'      => $groupId,
            'scope_level'   => $scopeLevel,
            'module_access' => $moduleAccess,
        ]);

        $user->companies()->sync($companiesToSync);

        $user->jobPositions()->sync($request->input('job_position_id', []));

        return redirect()
            ->route('users.index')
            ->with('success', "Usuario «{$user->name}» actualizado correctamente.");
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $authUser = auth()->user();

        // Mismo criterio que update(): un usuario sin empresa (alcance de grupo) se valida
        // por grupo, no por empresa (canAccessCompany(null) siempre da false).
        $canManageTarget = $user->company
            ? $authUser->canAccessCompany($user->company)
            : $authUser->canAccessGroup($user->group);

        if (! $authUser->isGlobalScope() && ! $canManageTarget) {
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