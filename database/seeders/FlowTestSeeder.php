<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Database\Seeder;

class FlowTestSeeder extends Seeder
{
    // Cada doc tiene un creador distinto (quien recibe notif. de aprobado/rechazado)
    // y un proceso distinto para distinguirlos visualmente
    private const DOCS = [
        [
            'level'          => 'bajo',
            'code'           => 'TEST-BAJO-01',
            'name'           => 'PRUEBA FLUJO BAJO',
            'doctype'        => 'Procedimiento',
            'process'        => 'Operaciones',
            'creator_email'  => 'gerente@vigia.com.mx',        // aprueba: ejecutivo
        ],
        [
            'level'          => 'medio',
            'code'           => 'TEST-MEDIO-01',
            'name'           => 'PRUEBA FLUJO MEDIO',
            'doctype'        => 'Reglamento',
            'process'        => 'Finanzas',
            'creator_email'  => 'direccion@vigia.com.mx',      // aprueba: ejecutivo → lider/gerente
        ],
        [
            'level'          => 'medio_alto',
            'code'           => 'TEST-MEDIOALTO-01',
            'name'           => 'PRUEBA FLUJO MEDIO-ALTO',
            'doctype'        => 'Instructivo',
            'process'        => 'Recursos Humanos',
            'creator_email'  => 'finanzas@vigia.com.mx',       // aprueba: lider+ejecutivo → direccion+finanzas
        ],
        [
            'level'          => 'alto',
            'code'           => 'TEST-ALTO-01',
            'name'           => 'PRUEBA FLUJO ALTO',
            'doctype'        => 'Política',
            'process'        => 'Seguridad',
            'creator_email'  => 'gerente@vigia.com.mx',        // aprueba: lider+ejecutivo → direccion
        ],
    ];

    public function run(): void
    {
        $group   = Group::where('slug', 'vigia')->firstOrFail();
        $company = Company::where('name', 'Empresa Demo')->firstOrFail();
        $flow    = app(ApprovalFlowService::class);

        foreach (self::DOCS as $doc) {
            $existing = Regulation::where('code', $doc['code'])->first();
            if ($existing) {
                $this->command->line("  Ya existe: {$doc['code']}, omitido.");
                continue;
            }

            $creator     = User::where('email', $doc['creator_email'])->firstOrFail();
            $processType = ProcessType::where('group_id', $group->id)
                ->where('name', $doc['process'])
                ->firstOrFail();

            $regulation = Regulation::create([
                'group_id'        => $group->id,
                'company_id'      => $company->id,
                'process_type_id' => $processType->id,
                'document_type'   => $doc['doctype'],
                'code'            => $doc['code'],
                'name'            => $doc['name'],
                'details'         => [],
                'is_active'       => true,
                'created_by'      => $creator->id,
                'impact_level'    => $doc['level'],
                'approval_status' => 'pending_review',
                'flow_locked'     => true,
            ]);

            $flow->initFlow($regulation);

            $this->command->info("  Creado: {$doc['code']} ({$doc['level']}) — creador: {$doc['creator_email']}");
        }
    }
}
