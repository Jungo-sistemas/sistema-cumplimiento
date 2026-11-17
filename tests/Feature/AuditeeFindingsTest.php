<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AuditCompletedNotification;
use App\Notifications\AuditFindingAssignedNotification;
use App\Notifications\AuditFindingEvidenceSubmittedNotification;
use App\Notifications\AuditFindingReviewedNotification;
use App\Services\AuditFindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Mis hallazgos (rol auditado): el auditor registra hallazgos y elige responsable; el responsable
 * sube evidencia (1 a 5 archivos); el auditor la acepta (cierra) o la rechaza con motivo y el
 * responsable ve el motivo y sube otra.
 */
class AuditeeFindingsTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;
    private User $ana;
    private User $luis;
    private Audit $audit;
    private AuditFindingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('private');
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(9, 0));
        $this->service = app(AuditFindingService::class);

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);
        $roleId = Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id;

        foreach (['auditor' => 'Pedro Auditor', 'ana' => 'Ana Torres', 'luis' => 'Luis Otro'] as $prop => $name) {
            $this->{$prop} = User::factory()->create([
                'name' => $name, 'group_id' => $group->id, 'company_id' => $company->id,
                'scope_level' => 'company', 'status' => 'active', 'role_id' => $roleId,
            ]);
        }

        $type = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-300', 'name' => 'Carga de autotanques', 'is_active' => true, 'approval_status' => 'approved',
        ]);

        $this->audit = Audit::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'regulation_id' => $regulation->id,
            'auditor_id' => $this->auditor->id, 'planned_for' => '2026-11-01', 'scheduled_at' => '2026-11-12 10:00:00',
        ]);
        $this->audit->auditees()->attach([$this->ana->id, $this->luis->id]);
    }

    private function finding(?User $responsible = null, string $point = 'Paso 4: bitácora de descarga'): AuditFinding
    {
        return $this->service->register($this->audit, $responsible ?? $this->ana, $point, 'No se llenó la bitácora.', 'mayor', now()->addDays(10));
    }

    private function upload(AuditFinding $finding, int $count = 2, array $extra = [])
    {
        $files = collect(range(1, $count))->map(fn ($i) => UploadedFile::fake()->create("evidencia{$i}.pdf", 100, 'application/pdf'))->all();

        return $this->actingAs($this->ana)->post(route('mi-espacio.findings.evidence', $finding), ['files' => $files] + $extra);
    }

    public function test_el_hallazgo_le_llega_solo_al_responsable_que_eligio_el_auditor(): void
    {
        $finding = $this->finding();

        Notification::assertSentTo($this->ana, AuditFindingAssignedNotification::class);
        Notification::assertNotSentTo($this->luis, AuditFindingAssignedNotification::class);

        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))
            ->assertOk()->assertSee('Paso 4: bitácora de descarga')->assertSee('Sube tu evidencia')->assertSee('27/11/2026');
        $this->actingAs($this->luis)->get(route('mi-espacio.findings'))
            ->assertOk()->assertDontSee('Paso 4: bitácora de descarga');

        // Inicio, menú y campana lo cuentan.
        $this->actingAs($this->ana)->get(route('mi-espacio.home'))
            ->assertSeeInOrder(['Mis hallazgos', '1'])
            ->assertSee('Hallazgos por atender')
            ->assertSee('Subir evidencia');

        $this->assertSame($finding->id, AuditFinding::forResponsible($this->ana)->value('id'));
    }

    public function test_el_responsable_tiene_que_ser_un_auditado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->register($this->audit, $this->auditor, 'Punto', 'Algo');
    }

    public function test_sube_evidencia_y_queda_en_revision_del_auditor(): void
    {
        $finding = $this->finding();

        $this->upload($finding, 3, ['comment' => 'Adjunto la bitácora firmada'])
            ->assertRedirect(route('mi-espacio.findings.show', $finding));

        $finding->refresh();
        $this->assertSame(AuditFinding::STATUS_IN_REVIEW, $finding->status);
        $submission = $finding->latestSubmission;
        $this->assertSame('Adjunto la bitácora firmada', $submission->comment);
        $this->assertCount(3, $submission->files);
        Storage::disk('private')->assertExists($submission->files->first()->path);
        Notification::assertSentTo($this->auditor, AuditFindingEvidenceSubmittedNotification::class);

        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))
            ->assertOk()->assertSee('En revisión del auditor')->assertSee('evidencia1.pdf')->assertDontSee('Enviar evidencia');

        // El auditor puede descargar; alguien ajeno a la auditoría no.
        $file = $submission->files->first();
        $this->actingAs($this->auditor)->get(route('mi-espacio.findings.file', $file))->assertOk();
        $outsider = User::factory()->create(['group_id' => $this->ana->group_id, 'status' => 'active', 'role_id' => $this->ana->role_id, 'scope_level' => 'group']);
        $this->actingAs($outsider)->get(route('mi-espacio.findings.file', $file))->assertForbidden();
    }

    public function test_maximo_cinco_archivos_y_al_menos_uno(): void
    {
        $finding = $this->finding();

        $this->upload($finding, 6)->assertSessionHasErrors('files');
        $this->actingAs($this->ana)->post(route('mi-espacio.findings.evidence', $finding), ['files' => []])->assertSessionHasErrors('files');
        $this->assertSame(AuditFinding::STATUS_PENDING, $finding->fresh()->status);

        $this->upload($finding, 5)->assertSessionHasNoErrors();
        $this->assertSame(AuditFinding::STATUS_IN_REVIEW, $finding->fresh()->status);
    }

    public function test_solo_el_responsable_sube_evidencia(): void
    {
        $finding = $this->finding();

        $this->actingAs($this->luis)
            ->post(route('mi-espacio.findings.evidence', $finding), ['files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
            ->assertForbidden();

        // Luis es auditado de la misma auditoría: puede ver el hallazgo, pero sin formulario.
        $this->actingAs($this->luis)->get(route('mi-espacio.findings.show', $finding))
            ->assertOk()->assertDontSee('Enviar evidencia');
    }

    public function test_rechazo_con_motivo_y_nueva_evidencia_hasta_cerrar(): void
    {
        $finding = $this->finding();
        $this->upload($finding, 1);

        $this->service->review($finding->fresh(), false, 'La foto no muestra la fecha de la bitácora.');
        Notification::assertSentTo($this->ana, AuditFindingReviewedNotification::class, fn ($n) => $n->submission->status === 'rechazada');

        $this->assertSame(AuditFinding::STATUS_REJECTED, $finding->fresh()->status);
        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))
            ->assertSee('Evidencia rechazada')->assertSee('La foto no muestra la fecha de la bitácora.');
        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))
            ->assertSee('El auditor rechazó tu evidencia')->assertSee('Subir nueva evidencia');

        // Sube otra: vuelve a revisión, y el historial conserva las dos.
        $this->upload($finding, 2)->assertSessionHasNoErrors();
        $this->assertSame(AuditFinding::STATUS_IN_REVIEW, $finding->fresh()->status);
        $this->assertSame(2, $finding->submissions()->count());

        $this->service->review($finding->fresh(), true);
        $finding->refresh();
        $this->assertSame(AuditFinding::STATUS_CLOSED, $finding->status);
        $this->assertNotNull($finding->closed_at);

        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))->assertDontSee('Paso 4: bitácora de descarga');
        $this->actingAs($this->ana)->get(route('mi-espacio.findings', ['tab' => 'concluidas']))
            ->assertSee('Paso 4: bitácora de descarga')->assertSee('Cerrado el 17/11/2026');
    }

    public function test_rechazar_sin_motivo_no_se_permite(): void
    {
        $finding = $this->finding();
        $this->upload($finding, 1);

        $this->expectException(InvalidArgumentException::class);
        $this->service->review($finding->fresh(), false, '  ');
    }

    public function test_la_calificacion_sale_en_historial_con_sus_hallazgos(): void
    {
        $this->finding();
        $this->finding($this->luis, 'Paso 7: firmas');

        $this->artisan('audits:complete', ['audit' => $this->audit->id, 'calificacion' => '85 / 100'])->assertSuccessful();
        Notification::assertSentTo([$this->ana, $this->luis], AuditCompletedNotification::class);

        $this->actingAs($this->ana)->get(route('mi-espacio.audits', ['tab' => 'historial']))
            ->assertOk()
            ->assertSee('Calificación')->assertSee('85 / 100')
            ->assertSee('2 hallazgos')->assertSee('2 abiertos')
            ->assertSee('Ver mis hallazgos (1)');
    }

    public function test_comandos_de_simulacion_del_auditor(): void
    {
        $this->artisan('audits:add-finding', [
            'audit' => $this->audit->id, 'responsable' => $this->ana->email,
            'punto' => 'Paso 2: EPP', 'descripcion' => 'Sin casco', '--severidad' => 'menor', '--vence' => '2026-12-01',
        ])->assertSuccessful();

        $finding = AuditFinding::where('point', 'Paso 2: EPP')->sole();
        $this->assertSame('2026-12-01', $finding->due_at->toDateString());

        $this->artisan('audits:review-evidence', ['hallazgo' => $finding->id, 'decision' => 'aceptar'])->assertFailed(); // aún sin evidencia
        $this->upload($finding, 1);
        $this->artisan('audits:review-evidence', ['hallazgo' => $finding->id, 'decision' => 'rechazar'])->assertFailed(); // falta motivo
        $this->artisan('audits:review-evidence', ['hallazgo' => $finding->id, 'decision' => 'rechazar', '--motivo' => 'Falta foto'])->assertSuccessful();
        $this->assertSame(AuditFinding::STATUS_REJECTED, $finding->fresh()->status);
    }
}
