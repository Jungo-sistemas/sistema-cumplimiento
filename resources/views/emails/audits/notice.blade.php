{{-- Correo genérico de auditorías: $headline, $intro, $rows (etiqueta => valor), $note (opcional),
     $noteTone ('blue' | 'red' | 'green'), $buttonUrl y $buttonLabel (opcionales). --}}
@php
    $tones = [
        'blue'  => ['#eff6ff', '#bfdbfe', '#1e3a8a'],
        'red'   => ['#fef2f2', '#fecaca', '#7f1d1d'],
        'green' => ['#f0fdf4', '#bbf7d0', '#14532d'],
    ];
    [$noteBg, $noteBorder, $noteText] = $tones[$noteTone ?? 'blue'] ?? $tones['blue'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $headline }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1A428A; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 13px; }
        .body { padding: 28px 32px; }
        .body p { color: #374151; line-height: 1.6; margin: 0 0 14px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .info-table td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 14px; vertical-align: top; }
        .info-table td:first-child { background: #f9fafb; font-weight: 600; color: #374151; width: 35%; }
        .note { border-radius: 6px; padding: 14px 16px; margin: 16px 0; font-size: 14px; line-height: 1.6; white-space: pre-line; }
        .btn { display: inline-block; background: #1A428A; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VIGIA Cumplimiento</h1>
            <p>{{ $headline }}</p>
        </div>
        <div class="body">
            <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
            <p>{{ $intro }}</p>

            <table class="info-table">
                @foreach($rows as $label => $value)
                    @if(filled($value))
                        <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
                    @endif
                @endforeach
            </table>

            @if(! empty($note))
                <div class="note" style="background: {{ $noteBg }}; border: 1px solid {{ $noteBorder }}; color: {{ $noteText }};">{{ $note }}</div>
            @endif

            @if(! empty($buttonUrl))
                <a href="{{ $buttonUrl }}" class="btn">{{ $buttonLabel ?? 'Ver en el sistema' }}</a>
            @endif
        </div>
        <div class="footer">
            Este correo fue generado automáticamente por VIGIA Cumplimiento.
        </div>
    </div>
</body>
</html>
