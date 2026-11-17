<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\InvestmentApprovalStep;
use App\Models\InvestmentEntity;
use App\Models\InvestmentRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InvestmentApprovalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Anexos 8: la lista "Empresa" es la de razones sociales del cliente, cada una con su flujo fijo de
 * aprobación. Al capturar se copia ese flujo y se avisa al primero; cada quien la ve en
 * "Mis aprobaciones de inversión".
 */
class InvestmentApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private InvestmentEntity $entity;
    private User $admin;
    private User $dd;
    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $this->entity  = InvestmentEntity::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id,
            'holding' => 'Vigia Combustibles', 'name' => 'Alcom', 'sort_order' => 1,
        ]);

        $adminRole = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $opRole    = Role::create(['name' => 'Operativo', 'slug' => 'operative']);

        $this->admin = User::factory()->create([
            'name' => 'Laura Admin', 'group_id' => $this->group->id, 'scope_level' => 'group',
            'status' => 'active', 'role_id' => $adminRole->id,
        ]);
        $this->dd = User::factory()->create([
            'name' => 'Federico Martínez Navarro', 'group_id' => $this->group->id, 'company_id' => $this->company->id,
            'scope_level' => 'company', 'status' => 'active', 'role_id' => $opRole->id,
        ]);
        $this->cfo = User::factory()->create([
            'name' => 'Luvia Hernandez', 'group_id' => $this->group->id, 'company_id' => $this->company->id,
            'scope_level' => 'company', 'status' => 'active', 'role_id' => $opRole->id,
        ]);
    }

    private function flow(array $steps, ?InvestmentEntity $entity = null): void
    {
        foreach (array_values($steps) as $i => [$position, $user]) {
            InvestmentApprovalStep::create([
                'investment_entity_id' => ($entity ?? $this->entity)->id,
                'sequence_order'       => $i + 1,
                'position'             => $position,
                'approver_name'        => $user instanceof User ? $user->name : $user,
                'user_id'              => $user instanceof User ? $user->id : null,
            ]);
        }
    }

    private function submit(?InvestmentEntity $entity = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('investment-requests.store'), [
            'entity_id' => ($entity ?? $this->entity)->id,
            'concept'   => 'Compra de tanque',
            'amount'    => '$150,000',
            'file'      => UploadedFile::fake()->create('Anexo 8 - Tanque.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_al_capturar_arranca_el_flujo_fijo_de_la_razon_social(): void
    {
        $this->flow([['DD', $this->dd], ['CFO', $this->cfo]]);

        // El modal recibe el flujo de cada razón social para mostrarlo al elegirla.
        $this->actingAs($this->admin)->get(route('investment-requests.index'))
            ->assertOk()->assertSee('Federico Martínez Navarro', false)->assertDontSee('approver_ids');

        $this->submit()->assertRedirect(route('investment-requests.index'));

        $request = InvestmentRequest::sole();
        $this->assertSame([$this->company->id, $this->entity->id], [$request->company_id, $request->investment_entity_id]);
        $this->assertSame('Alcom', $request->displayCompanyName());
        $this->assertSame(
            [['DD', $this->dd->id, 'pending'], ['CFO', $this->cfo->id, 'waiting']],
            $request->approvals->map(fn ($a) => [$a->position, $a->user_id, $a->status])->all()
        );
        Notification::assertSentTo($this->dd, InvestmentApprovalRequestedNotification::class);
        Notification::assertNotSentTo($this->cfo, InvestmentApprovalRequestedNotification::class);
    }

    public function test_la_lista_es_la_de_razones_sociales_en_su_orden(): void
    {
        // Dos razones sociales de la misma empresa, una sin empresa en el sistema y una oculta.
        $biomex = Company::create(['name' => 'BIOMEX', 'group_id' => $this->group->id]);
        InvestmentEntity::create(['group_id' => $this->group->id, 'company_id' => $biomex->id, 'holding' => 'Biomex', 'name' => 'Parque Estrella', 'sort_order' => 3]);
        InvestmentEntity::create(['group_id' => $this->group->id, 'company_id' => $biomex->id, 'holding' => 'Biomex', 'name' => 'IBPME', 'sort_order' => 4]);
        InvestmentEntity::create(['group_id' => $this->group->id, 'holding' => 'Vigia Combustibles', 'name' => 'Grupo Vigía', 'sort_order' => 2]);
        InvestmentEntity::create(['group_id' => $this->group->id, 'name' => 'Vieja', 'sort_order' => 5, 'is_active' => false]);
        Company::create(['name' => 'CAPITAL HUMANO', 'group_id' => $this->group->id]);

        $entities = $this->actingAs($this->admin)->get(route('investment-requests.index'))
            ->assertOk()->assertDontSee('CAPITAL HUMANO')->viewData('entities');

        $this->assertSame(['Alcom', 'Grupo Vigía', 'Parque Estrella', 'IBPME'], $entities->pluck('name')->all());

        // Quien es de una sola empresa solo ve las razones sociales de esa empresa.
        $entities = $this->actingAs($this->dd)->get(route('investment-requests.index'))->viewData('entities');
        $this->assertSame(['Alcom'], $entities->pluck('name')->all());
    }

    public function test_una_razon_social_sin_empresa_queda_a_nivel_grupo(): void
    {
        $grupo = InvestmentEntity::create(['group_id' => $this->group->id, 'name' => 'Grupo Vigía', 'sort_order' => 2]);
        $this->flow([['CFO', $this->cfo]], $grupo);

        $this->submit($grupo)->assertRedirect(route('investment-requests.index'));

        $request = InvestmentRequest::sole();
        $this->assertNull($request->company_id);
        $this->assertSame('Grupo Vigía', $request->displayCompanyName());
        Notification::assertSentTo($this->cfo, InvestmentApprovalRequestedNotification::class);
    }

    public function test_no_se_puede_capturar_sin_flujo_o_con_personas_sin_usuario(): void
    {
        $this->submit()->assertSessionHasErrorsIn('createInvestmentRequest', 'entity_id');

        $this->flow([['Gerente', 'Vicente Saint Martin Ochoa'], ['DD', $this->dd]]);

        $this->submit()->assertSessionHasErrorsIn('createInvestmentRequest', [
            'entity_id' => 'Falta dar de alta en el sistema a: Vicente Saint Martin Ochoa (Gerente).',
        ]);

        // Ni una razón social que no está en su lista.
        $otherGroup = Group::create(['name' => 'Otro', 'slug' => 'otro', 'is_active' => true]);
        $ajena = InvestmentEntity::create(['group_id' => $otherGroup->id, 'name' => 'Ajena']);
        $this->submit($ajena)->assertSessionHasErrorsIn('createInvestmentRequest', 'entity_id');

        $this->assertSame(0, InvestmentRequest::count());
    }

    public function test_cada_aprobador_ve_su_turno_en_mis_aprobaciones(): void
    {
        $this->flow([['DD', $this->dd], ['CFO', $this->cfo]]);
        $this->submit();
        $request = InvestmentRequest::sole();

        $this->actingAs($this->dd)->get(route('investment-requests.my-approvals'))
            ->assertOk()->assertSee('Compra de tanque')->assertSee('Mis aprobaciones de inversión');
        $this->actingAs($this->cfo)->get(route('investment-requests.my-approvals'))
            ->assertOk()->assertSee('Todo al día');

        // La campana dice de qué tipo es la aprobación.
        $this->actingAs($this->dd)->get(route('investment-requests.my-approvals'))
            ->assertSee('Aprobación de solicitudes de inversión (Anexos 8)')
            ->assertDontSee('Aprobación de procesos');

        $this->actingAs($this->dd)->post(route('investment-requests.approve', $request))->assertRedirect();

        $this->actingAs($this->cfo)->get(route('investment-requests.my-approvals'))
            ->assertOk()->assertDontSee('Todo al día')->assertSee('Compra de tanque');
        $this->actingAs($this->dd)->get(route('investment-requests.my-approvals'))
            ->assertSee('Todo al día')->assertSee('Aprobó');
    }

    public function test_un_aprobador_sin_cumplimiento_aprueba_desde_procesos_y_solo_ve_lo_suyo(): void
    {
        $this->cfo->update(['module_access' => 'procesos', 'scope_level' => 'group', 'company_id' => null]);
        $this->flow([['DD', $this->dd], ['CFO', $this->cfo]]);
        $this->submit();
        $request = InvestmentRequest::sole();
        $this->actingAs($this->dd)->post(route('investment-requests.approve', $request));

        // Le llega en la campana y en el menú de Procesos; abre y aprueba la solicitud.
        $this->actingAs($this->cfo)->get(route('investment-requests.my-approvals'))
            ->assertOk()->assertSee('Compra de tanque')->assertSee('Aprobación de solicitudes de inversión (Anexos 8)');
        $this->actingAs($this->cfo)->get(route('investment-requests.show', $request))
            ->assertOk()->assertSee(route('investment-requests.my-approvals'), false);
        $this->actingAs($this->cfo)->post(route('investment-requests.approve', $request))->assertRedirect();
        $this->assertSame('approved', $request->fresh()->status->value);

        // Pero no el resto de Cumplimiento ni solicitudes donde no está en el flujo.
        $this->actingAs($this->cfo)->get(route('investment-requests.index'))->assertRedirect();
        $this->actingAs($this->cfo)->get(route('assets.index'))->assertRedirect();

        $this->flow([['Gerente', $this->dd]], $otra = InvestmentEntity::create(['group_id' => $this->group->id, 'company_id' => $this->company->id, 'name' => 'Otra']));
        $this->submit($otra);
        $this->actingAs($this->cfo)->get(route('investment-requests.show', InvestmentRequest::latest('id')->first()))->assertForbidden();
    }

    public function test_el_comando_carga_la_lista_y_liga_a_las_personas_por_nombre_sin_acentos(): void
    {
        Company::create(['name' => 'BIOMEX', 'group_id' => $this->group->id]);

        $this->artisan('investments:sync-approval-flows')->assertSuccessful();

        $alcom = InvestmentEntity::where('name', 'Alcom')->sole();
        $this->assertSame($this->company->id, $alcom->company_id);
        $this->assertSame($this->entity->id, $alcom->id); // la que ya existía se actualiza, no se duplica

        $steps = $alcom->steps->keyBy('position');
        $this->assertSame(['Gerente', 'DD', 'CFO', 'CEO', 'Consejero'], $steps->keys()->all());
        $this->assertSame('Vicente Saint Martin Ochoa', $steps['Gerente']->approver_name);
        $this->assertNull($steps['Gerente']->user_id);              // aún no tiene usuario
        $this->assertSame($this->dd->id, $steps['DD']->user_id);    // "Federico Martinez Navarro" sin acento
        $this->assertSame($this->cfo->id, $steps['CFO']->user_id);

        // Toda la lista del cliente, aunque la empresa no exista todavía en el sistema.
        $this->assertSame(26, InvestmentEntity::where('is_active', true)->count());
        $this->assertNull(InvestmentEntity::where('name', 'Grupo Vigía')->sole()->company_id);
        $this->assertSame(2, InvestmentEntity::whereHas('company', fn ($q) => $q->where('name', 'BIOMEX'))->count());
    }
}
