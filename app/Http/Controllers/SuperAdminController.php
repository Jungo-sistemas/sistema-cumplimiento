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
                $group->current_license = $this->licenseService->currentLicense($group);
                return $group;
            });

        return view('superadmin.groups', compact('groups'));
    }

    public function activateGroupLicense(Request $request, Group $group)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $this->licenseService->activate($group, $request->boolean('includes_procesos'), auth()->user());

        return back()->with('success', 'Licencia del grupo Â«' . $group->name . 'Â» activada por 1 mes.');
    }

    public function updateGroupLimit(Request $request, Group $group)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'asset_limit' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $group->update(['asset_limit' => $request->filled('asset_limit') ? (int) $request->asset_limit : null]);

        return back()->with('success', 'LÃ­mite del grupo "' . $group->name . '" actualizado.');
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
                $company->current_license = $this->licenseService->currentLicense(
                    $this->licenseService->resolveLicensable($company)
                );
                $company->license_scope_group = $this->licenseService->resolveLicensable($company) instanceof Group;
                return $company;
            });

        $groups = Group::orderBy('name')->get();

        return view('superadmin.companies', compact('companies', 'groups'));
    }

    public function activateCompanyLicense(Request $request, Company $company)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $licensable = $this->licenseService->resolveLicensable($company);

        if ($licensable instanceof Group) {
            return back()->with('error', 'Esta empresa se licencia a travÃ©s de su grupo Â«' . $licensable->name . 'Â» â€” activa la licencia desde la secciÃ³n de Grupos.');
        }

        $this->licenseService->activate($company, $request->boolean('includes_procesos'), auth()->user());

        return back()->with('success', 'Licencia de Â«' . $company->name . 'Â» activada por 1 mes.');
    }

    public function updateCompanyLimit(Request $request, Company $company)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'asset_limit' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $company->update(['asset_limit' => $request->filled('asset_limit') ? (int) $request->asset_limit : null]);

        return back()->with('success', 'LÃ­mite de "' . $company->name . '" actualizado.');
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

    public function users(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $search    = trim((string) $request->query('q', ''));
        $groupId   = $request->query('group_id');
        $companyId = $request->query('company_id');

        $users = User::with(['role', 'company', 'group', 'jobPositions', 'companies'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
            ))
            ->when($groupId, fn ($query) => $query->where('group_id', $groupId))
            ->when($companyId, fn ($query) => $query->where(
                fn ($q) => $q->where('company_id', $companyId)
                    ->orWhereHas('companies', fn ($qc) => $qc->where('companies.id', $companyId))
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles            = Role::orderBy('name')->get();
        $companies        = Company::orderBy('name')->get(['id', 'name', 'group_id', 'process_name', 'show_in_processes']);
        $companiesByGroup = $companies->groupBy('group_id')->map->values();
        $groups           = Group::orderBy('name')->get();
        $jobPositions     = JobPosition::where('is_active', true)
            ->orderBy('group_id')
            ->orderBy('sort_order')
            ->get(['id', 'group_id', 'name']);

        return view('superadmin.users', compact('users', 'roles', 'companies', 'companiesByGroup', 'groups', 'jobPositions', 'search', 'groupId', 'companyId'));
    }

    public function storeUser(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id'       => ['required', 'exists:roles,id'],
            'company_id'    => ['nullable', 'array'],
            'company_id.*'  => ['exists:companies,id'],
            'group_id'      => ['nullable', 'exists:groups,id'],
            'module_access' => ['nullable', 'in:all,cumplimiento,procesos'],
            'notification_modules' => ['nullable', 'in:all,cumplimiento,procesos,none'],
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->slug === 'superadmin') {
            $scopeLevel      = 'global';
            $companyId       = null;
            $groupId         = null;
            $moduleAccess    = 'all';
            $companiesToSync = [];
        } else {
            // Un admin puede quedarse con alcance de grupo completo (igual que antes, dejando las
            // empresas sin seleccionar) o reducirse a una/varias empresas especÃ­ficas.
            [$companyId, $resolvedGroupId, $scopeLevel, $companiesToSync] = $this->resolveSuperAdminCompanyScope(
                $request->input('company_id', []),
                $request->group_id,
            );
            $groupId      = $resolvedGroupId ?? $request->group_id;
        }
        [$moduleAccess, $portalModules] = $this->resolveSuperAdminModules($request, $role);

        // A diferencia de module_access, esto no se fuerza a 'all' para superadmin: un superadmin
        // tambiÃ©n puede querer notificaciones de una sola Ã¡rea aunque tenga acceso a ambas.
        [$notificationModules, $notifyModules] = $this->resolveSuperAdminNotifications($request, $role, $moduleAccess, $portalModules);

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'role_id'           => $role->id,
            'company_id'        => $companyId,
            'group_id'          => $groupId,
            'scope_level'       => $scopeLevel,
            'module_access'     => $moduleAccess,
            'portal_modules'    => $portalModules,
            'notification_modules' => $notificationModules,
            'notify_modules'    => $notifyModules,
            'receives_audit_alerts' => $request->boolean('receives_audit_alerts'),
            'password'          => null,
            'status'            => 'invited',
            'invite_token'      => Str::random(64),
            'invite_expires_at' => now()->addDays(3),
            'invited_by'        => auth()->id(),
        ]);

        if (! empty($companiesToSync)) {
            $user->companies()->sync($companiesToSync);
        }

        $positionIds = array_filter((array) $request->input('job_position_ids', []));
        if (!empty($positionIds)) {
            $user->jobPositions()->attach($positionIds);
        }

        Mail::to($user->email)->send(new UserInvitationMail($user));

        return redirect()
            ->route('superadmin.users')
            ->with('success', 'Usuario invitado correctamente.');
    }

    /**
     * [module_access, portal_modules] de las casillas modules[] (los seis de PortalModules). El
     * superadmin tiene todo; sin casillas se acepta el module_access de antes (null = 'all').
     *
     * @return array{0: string, 1: ?array}
     */
    private function resolveSuperAdminModules(Request $request, Role $role): array
    {
        if ($role->slug === 'superadmin') {
            return ['all', null];
        }

        if ($request->has('modules')) {
            $request->validate(
                [
                    'modules'   => ['required', 'array', 'min:1'],
                    'modules.*' => ['in:' . implode(',', array_keys(\App\Support\PortalModules::LABELS))],
                ],
                ['modules.required' => 'Marca al menos un mÃ³dulo.', 'modules.min' => 'Marca al menos un mÃ³dulo.']
            );
            $selection = \App\Support\PortalModules::fromSelection($request->input('modules'));

            return [$selection['module_access'], $selection['portal_modules']];
        }

        return [in_array($request->module_access, ['all', 'cumplimiento', 'procesos'], true) ? $request->module_access : 'all', null];
    }

    /**
     * [notification_modules, notify_modules]. Con las pastillas por mÃ³dulo (notify_modules[]) solo
     * cuentan los mÃ³dulos que tendrÃ¡ el usuario y el Ã¡rea se deriva de ellos; sin pastillas
     * marcadas (o con la forma anterior) se usa notification_modules y no hay elecciÃ³n por mÃ³dulo.
     *
     * @return array{0: ?string, 1: ?array}
     */
    private function resolveSuperAdminNotifications(Request $request, Role $role, string $moduleAccess, ?array $portalModules): array
    {
        if ($request->has('notify_modules')) {
            $userModules = match ($role->slug) {
                'superadmin' => array_keys(\App\Support\PortalModules::LABELS),
                'auditor'    => ['procesos'],
                default      => (new User(['module_access' => $moduleAccess, 'portal_modules' => $portalModules]))->portalModules(),
            };
            $notify = array_values(array_intersect($userModules, (array) $request->input('notify_modules', [])));

            $cumplimiento = array_intersect(\App\Support\PortalModules::cumplimientoModules(), $notify) !== [];
            $procesos = in_array('procesos', $notify, true);

            return [match (true) {
                $cumplimiento && $procesos => 'all',
                $cumplimiento              => 'cumplimiento',
                $procesos                  => 'procesos',
                default                    => 'none',
            }, $notify];
        }

        $legacy = in_array($request->notification_modules, ['all', 'cumplimiento', 'procesos', 'none'], true)
            ? $request->notification_modules : null;

        return [$legacy, null];
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'role_id'       => ['required', 'exists:roles,id'],
            'company_id'    => ['nullable', 'array'],
            'company_id.*'  => ['exists:companies,id'],
            'group_id'      => ['nullable', 'exists:groups,id'],
            'module_access' => ['nullable', 'in:all,cumplimiento,procesos'],
            'notification_modules' => ['nullable', 'in:all,cumplimiento,procesos,none'],
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->slug === 'superadmin') {
            $scopeLevel      = 'global';
            $companyId       = null;
            $groupId         = null;
            $moduleAccess    = 'all';
            $companiesToSync = [];
        } else {
            [$companyId, $resolvedGroupId, $scopeLevel, $companiesToSync] = $this->resolveSuperAdminCompanyScope(
                $request->input('company_id', []),
                $request->group_id,
            );
            $groupId      = $resolvedGroupId ?? $request->group_id;
        }
        [$moduleAccess, $portalModules] = $this->resolveSuperAdminModules($request, $role);

        [$notificationModules, $notifyModules] = $this->resolveSuperAdminNotifications($request, $role, $moduleAccess, $portalModules);

        $user->update([
            'role_id'       => $role->id,
            'company_id'    => $companyId,
            'group_id'      => $groupId,
            'scope_level'   => $scopeLevel,
            'module_access' => $moduleAccess,
            'portal_modules' => $portalModules,
            'notification_modules' => $notificationModules,
            'notify_modules' => $notifyModules,
        ]);

        if ($request->has('receives_audit_alerts')) {
            $user->update(['receives_audit_alerts' => $request->boolean('receives_audit_alerts')]);
        }

        $user->companies()->sync($companiesToSync);

        $positionIds = array_filter((array) $request->input('job_position_ids', []));
        $user->jobPositions()->sync($positionIds);

        return redirect()
            ->route('superadmin.users')
            ->with('success', "Usuario Â«{$user->name}Â» actualizado correctamente.");
    }

    /**
     * Mismo criterio que UserController::resolveCompanyScope(), sin el chequeo de
     * canAccessCompany() â€” el superadmin no tiene restricciÃ³n de quÃ© empresas puede asignar.
     *
     * @param  array<int, mixed>  $companyIds
     * @return array{0: ?int, 1: ?int, 2: string, 3: array<int, int>} [companyId, groupId, scopeLevel, companiesToSync]
     */
    private function resolveSuperAdminCompanyScope(array $companyIds, ?int $fallbackGroupId): array
    {
        $ids = collect($companyIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [null, $fallbackGroupId, 'group', []];
        }

        $companies = Company::whereIn('id', $ids)->get();

        abort_if($companies->count() !== $ids->count(), 422, 'Una o mÃ¡s empresas seleccionadas no existen.');

        if ($companies->count() === 1) {
            $company = $companies->first();

            return [$company->id, $company->group_id, 'company', []];
        }

        abort_if($companies->pluck('group_id')->unique()->count() > 1, 422, 'Las empresas seleccionadas deben pertenecer al mismo grupo.');

        return [null, $companies->first()->group_id, 'companies', $ids->all()];
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
            ->with('success', $user->deletedMessage());
    }

    /**
     * Mismo criterio que UserController::resendInvitation() â€” regenera el token/vigencia y
     * reenvÃ­a el correo, para cuando el enlace original se perdiÃ³, venciÃ³ (3 dÃ­as) o nunca llegÃ³.
     */
    public function resendInvitationUser(User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($user->status !== 'invited') {
            return back()->with('error', 'Este usuario ya activÃ³ su cuenta â€” no aplica reenviar invitaciÃ³n.');
        }

        $user->update([
            'invite_token'      => Str::random(64),
            'invite_expires_at' => now()->addDays(3),
        ]);

        Mail::to($user->email)->send(new UserInvitationMail($user));

        return back()->with('success', "InvitaciÃ³n reenviada a {$user->email}.");
    }
}
