@php $regulation = $audit->regulation; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Este mes te toca una auditoría</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1A428A; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 13px; }
        .body { padding: 28px 32px; }
        .body p { color: #374151; line-height: 1.6; margin: 0 0 14px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .info-table td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 14px; }
        .info-table td:first-child { background: #f9fafb; font-weight: 600; color: #374151; width: 40%; }
        .note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px 16px; margin: 16px 0; color: #1e3a8a; font-size: 14px; line-height: 1.6; }
        .btn { display: inline-block; background: #1A428A; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VIGIA Cumplimiento</h1>
            <p>Auditoría programada para {{ $audit->plannedMonthLabel() }}</p>
        </div>
        <div class="body">
            <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
            <p>Este mes te toca una auditoría del siguiente proceso:</p>

            <table class="info-table">
                <tr><td>Proceso</td><td><strong>{{ $regulation->code ? $regulation->code . ' — ' : '' }}{{ $regulation->name }}</strong></td></tr>
                <tr><td>Empresa</td><td>{{ $audit->company?->procesos_name ?? $audit->company?->name ?? '—' }}</td></tr>
                <tr><td>Mes</td><td>{{ ucfirst($audit->plannedMonthLabel()) }}</td></tr>
                <tr><td>Auditor</td><td>{{ $audit->auditor?->name ?? 'Por asignar' }}</td></tr>
            </table>

            <div class="note">
                El auditor se pondrá de acuerdo contigo para el día y la hora; la auditoría es en vivo por Teams.
                Revisa el checklist del proceso y ten listas tus evidencias.
            </div>

            <a href="{{ route('mi-espacio.audits') }}" class="btn">Ver mis auditorías</a>
        </div>
        <div class="footer">
            Este correo fue generado automáticamente por VIGIA Cumplimiento.
        </div>
    </div>
</body>
</html>
