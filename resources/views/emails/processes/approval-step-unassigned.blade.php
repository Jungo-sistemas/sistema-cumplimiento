<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flujo de aprobación detenido</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #B91C1C; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 13px; }
        .body { padding: 28px 32px; }
        .body p { color: #374151; line-height: 1.6; margin: 0 0 14px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .info-table td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 14px; }
        .info-table td:first-child { background: #f9fafb; font-weight: 600; color: #374151; width: 40%; }
        .btn { display: inline-block; background: #1A428A; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VIGIA Cumplimiento</h1>
            <p>Flujo de aprobación detenido</p>
        </div>
        <div class="body">
            <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
            <p>El paso {{ $step }} del flujo de aprobación de este documento no tiene a nadie que pueda
               decidirlo: {{ count($missingPositions) === 1 ? 'el puesto' : 'los puestos' }}
               <strong>{{ implode(', ', $missingPositions) }}</strong>
               no {{ count($missingPositions) === 1 ? 'tiene' : 'tienen' }} ningún usuario asignado
               {{ count($missingPositions) === 1 ? 'en este' : 'en esta empresa' }}. El documento se
               quedará en "En autorización" indefinidamente hasta que se asigne a alguien a ese puesto
               y el flujo se reenvíe.</p>

            <table class="info-table">
                <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
                <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
                <tr><td>Empresa</td><td>{{ $regulation->company->name ?? '—' }}</td></tr>
                <tr><td>Paso detenido</td><td>{{ $step }}</td></tr>
            </table>

            <a href="{{ route('processes.flow', $regulation) }}" class="btn">Asignar el puesto faltante</a>
        </div>
        <div class="footer">
            Este correo fue generado automáticamente por VIGIA Cumplimiento. Por favor no respondas a este mensaje.
        </div>
    </div>
</body>
</html>
