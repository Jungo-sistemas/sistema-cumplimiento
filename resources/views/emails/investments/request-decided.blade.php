<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $approved ? 'Solicitud aprobada' : 'Solicitud rechazada' }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: {{ $approved ? '#16a34a' : '#dc2626' }}; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 13px; }
        .body { padding: 28px 32px; }
        .body p { color: #374151; line-height: 1.6; margin: 0 0 14px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .info-table td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 14px; }
        .info-table td:first-child { background: #f9fafb; font-weight: 600; color: #374151; width: 40%; }
        .comment-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 14px 16px; margin: 16px 0; color: #7f1d1d; font-size: 14px; line-height: 1.6; }
        .btn { display: inline-block; background: #1A428A; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VIGIA Cumplimiento</h1>
            <p>{{ $approved ? '✓ Solicitud de inversión aprobada' : '✗ Solicitud de inversión rechazada' }}</p>
        </div>
        <div class="body">
            <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
            @if($approved)
                <p>La solicitud de inversión fue <strong>aprobada</strong> por todas las personas del flujo.</p>
            @else
                <p>La solicitud de inversión fue <strong>rechazada</strong>{{ $rejectedBy ? ' por ' . $rejectedBy->name : '' }}.</p>
            @endif

            <table class="info-table">
                <tr><td>Motivo</td><td><strong>{{ $investmentRequest->concept }}</strong></td></tr>
                <tr><td>Empresa</td><td>{{ $investmentRequest->company->name ?? '—' }}</td></tr>
                <tr><td>Monto</td><td>${{ number_format($investmentRequest->amount) }}</td></tr>
                <tr><td>Solicitante</td><td>{{ $investmentRequest->requester->name ?? '—' }}</td></tr>
                <tr>
                    <td>Flujo</td>
                    <td>
                        @foreach($approvals as $a)
                            {{ $a->sequence_order }}. {{ $a->user?->name ?? '—' }} — {{ $a->statusLabel() }}<br>
                        @endforeach
                    </td>
                </tr>
            </table>

            @if(! $approved && $comments)
                <p><strong>Motivo del rechazo:</strong></p>
                <div class="comment-box">{{ $comments }}</div>
            @endif

            <a href="{{ route('investment-requests.show', $investmentRequest) }}" class="btn">Ver solicitud</a>
        </div>
        <div class="footer">
            Este correo fue generado automáticamente por VIGIA Cumplimiento.
        </div>
    </div>
</body>
</html>
