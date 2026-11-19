<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada flujo de aprobación guarda los pasos con los que arrancó (regulations.flow_steps), para que
 * cambiar la definición de los flujos (ApprovalFlowService::FLOWS) solo afecte a los que se
 * asignen después. Antes, al pasar de un paso al siguiente se leía la definición vigente, así que
 * un flujo abierto podía cambiar a mitad de camino.
 *
 * Los flujos abiertos hoy se quedan con la regla que tenían (la de antes del 19-nov-2026):
 *   bajo: Líder · medio: Líder → Jefe o Gerente (basta uno) · medio alto: Líder → Gerente →
 *   Dirección/Consejo · alto: Líder → Jefe → Gerente → Dirección/Consejo · confidencial: Consejo (basta uno).
 */
return new class extends Migration
{
    private const PREVIOUS_FLOWS = [
        'bajo' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
        ],
        'medio' => [
            1 => ['requires_all' => true,  'positions' => ['lider']],
            2 => ['requires_all' => false, 'positions' => ['jefe', 'gerente']],
        ],
        'medio_alto' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
            2 => ['requires_all' => true, 'positions' => ['gerente']],
            3 => ['requires_all' => true, 'positions' => ['direccion', 'consejo']],
        ],
        'alto' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
            2 => ['requires_all' => true, 'positions' => ['jefe']],
            3 => ['requires_all' => true, 'positions' => ['gerente']],
            4 => ['requires_all' => true, 'positions' => ['direccion', 'consejo']],
        ],
    ];

    private const PREVIOUS_RESTRICTED_FLOW = [
        1 => ['requires_all' => false, 'positions' => ['consejo']],
    ];

    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->json('flow_steps')->nullable()->after('flow_user_map');
        });

        $open = DB::table('regulations')
            ->leftJoin('companies', 'companies.id', '=', 'regulations.company_id')
            ->whereIn('regulations.approval_status', ['pending_review', 'pending_authorization'])
            ->whereNotNull('regulations.impact_level')
            ->get(['regulations.id', 'regulations.impact_level', 'companies.is_restricted']);

        foreach ($open as $regulation) {
            $steps = $regulation->is_restricted
                ? self::PREVIOUS_RESTRICTED_FLOW
                : (self::PREVIOUS_FLOWS[$regulation->impact_level] ?? null);

            if ($steps) {
                DB::table('regulations')->where('id', $regulation->id)->update(['flow_steps' => json_encode($steps)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropColumn('flow_steps');
        });
    }
};
