<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobación requerida</title>
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
        .badge { display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .btn { display: inline-block; background: #1A428A; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VIGIA Cumplimiento</h1>
            <p>Se requiere tu aprobación</p>
        </div>
        <div class="body">
            <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
            <p>Se ha creado un nuevo reglamento que requiere tu aprobación. A continuación los detalles:</p>

            <table class="info-table">
                <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
                <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
                <tr><td>Tipo</td><td>{{ $regulation->document_type ?? '—' }}</td></tr>
                <tr><td>Empresa</td><td>{{ $regulation->company->name ?? '—' }}</td></tr>
                <tr>
                    <td>Nivel de impacto</td>
                    <td>
                        @php
                            $level = $regulation->impact_level;
                            $badge = match($level) {
                                'alto' => 'badge-red',
                                'medio_alto' => 'badge-yellow',
                                'medio' => 'badge-blue',
                                default => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $badge }}">{{ $regulation->impactLevelLabel() }}</span>
                    </td>
                </tr>
            </table>

            <p>Ingresa al sistema para revisar el documento completo y emitir tu decisión.</p>

            <a href="{{ route('processes.show', $regulation) }}" class="btn">Ver reglamento</a>
        </div>
        <div class="footer">
            Este correo fue generado automáticamente por VIGIA Cumplimiento. Por favor no respondas a este mensaje.
        </div>
    </div>
</body>
</html>
