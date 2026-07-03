<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo procedimiento disponible</title>
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
        .btn { display: inline-block; background: #1A428A; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .note { font-size: 12px; color: #9ca3af; margin-top: 16px; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VIGIA Cumplimiento</h1>
            <p>Nuevo procedimiento disponible para ti</p>
        </div>
        <div class="body">
            <p>Hola <strong>{{ $share->recipient->name }}</strong>,</p>
            <p>
                <strong>{{ $sender->name }}</strong> te compartió un procedimiento aprobado
                para que lo revises.
            </p>

            <table class="info-table">
                <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
                @if($regulation->code)
                <tr><td>Código</td><td>{{ $regulation->code }}</td></tr>
                @endif
                <tr><td>Tipo</td><td>{{ $regulation->document_type ?? '—' }}</td></tr>
                <tr><td>Empresa</td><td>{{ $regulation->company->name ?? '—' }}</td></tr>
            </table>

            <a href="{{ route('processes.view-track', [$regulation, $share->token]) }}" class="btn">
                Ver procedimiento
            </a>

            <p class="note">
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                {{ route('processes.view-track', [$regulation, $share->token]) }}
            </p>
        </div>
        <div class="footer">
            Este correo fue generado automáticamente por VIGIA Cumplimiento.
        </div>
    </div>
</body>
</html>
