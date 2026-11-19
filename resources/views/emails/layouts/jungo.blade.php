{{--
    Plantilla de todos los correos, con la estética de Jungo Cumple (mismos colores y tipografía
    que la plataforma: tailwind.config.js → brand).

    Uso:
        @extends('emails.layouts.jungo')
        @section('title', 'Documento aprobado')
        @section('headline', '✓ Documento aprobado')
        @section('tone', 'success')
        @section('content') … @endsection

    - title:    <title> del correo.
    - headline: texto bajo el logo, en el encabezado (acepta Blade: @section('headline') … @endsection).
    - tone:     franja de color bajo el encabezado — brand (default) | success | danger | warning.
    - footer:   opcional, reemplaza el pie genérico.

    El logo va incrustado en el correo (cid) en vez de enlazado: así se ve aunque el servidor no
    sea accesible desde fuera, y es PNG porque Gmail y Outlook no muestran SVG. $message solo
    existe al enviarse; en la vista previa o en pruebas se usa la URL pública.

    Clases disponibles en el contenido: info-table, approvers-table (td.head), data-table, btn,
    badge (+ badge-red/-yellow/-blue/-gray/-green), note, comment-box, alert (+ alert-danger/
    -warning/-orange/-info/-success), code-box, muted, small.
--}}
@php
    $accentColors = [
        'brand'   => '#5B1690',
        'success' => '#16A34A',
        'danger'  => '#DC2626',
        'warning' => '#F59E0B',
    ];
    $toneColor = $accentColors[trim($__env->yieldContent('tone', 'brand'))] ?? $accentColors['brand'];

    $logoPath = public_path('images/email/jungo-cumple-blanco.png');
    $logoSrc  = (isset($message) && $message instanceof \Illuminate\Mail\Message && is_file($logoPath))
        ? $message->embed($logoPath)
        : asset('images/email/jungo-cumple-blanco.png');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', 'Jungo Cumple')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #F7F7FA; -webkit-text-size-adjust: 100%; }
        body, td, th, p, a, span, div { font-family: 'Inter', 'Segoe UI', Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background: #F7F7FA; padding: 32px 12px; }
        .container { max-width: 600px; width: 100%; margin: 0 auto; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 16px; overflow: hidden; }
        .header { background: #21004B; padding: 24px 32px; }
        .header img { display: block; height: 30px; width: auto; border: 0; }
        .header .headline { margin: 14px 0 0; color: #FFFFFF; font-size: 17px; font-weight: 600; line-height: 1.4; }
        .accent { height: 4px; line-height: 4px; font-size: 0; }
        .body { padding: 28px 32px 32px; color: #374151; font-size: 14px; line-height: 1.6; }
        .body p { color: #374151; font-size: 14px; line-height: 1.6; margin: 0 0 14px; }
        .body h2 { color: #21004B; font-size: 18px; font-weight: 700; margin: 0 0 14px; }
        .body strong { color: #111827; }
        a { color: #5B1690; }

        .info-table, .approvers-table, .data-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 16px 0 20px; border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; }
        .info-table td, .approvers-table td, .data-table td, .data-table th { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #E5E7EB; vertical-align: top; text-align: left; }
        .info-table tr:last-child td, .approvers-table tr:last-child td, .data-table tr:last-child td { border-bottom: 0; }
        .info-table td:first-child { background: #F7F7FA; color: #21004B; font-weight: 600; width: 38%; }
        .approvers-table td.head, .data-table th { background: #F5EEFB; color: #21004B; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }

        .btn { display: inline-block; background: #5B1690; color: #FFFFFF !important; text-decoration: none; padding: 12px 26px; border-radius: 8px; font-weight: 600; font-size: 14px; margin-top: 8px; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-red { background: #FEE2E2; color: #991B1B; }
        .badge-yellow { background: #FEF3C7; color: #92400E; }
        .badge-blue { background: #F5EEFB; color: #5B1690; }
        .badge-gray { background: #F3F4F6; color: #374151; }
        .badge-green { background: #DCFCE7; color: #166534; }

        .note { background: #F5EEFB; border: 1px solid #EADCF7; border-radius: 10px; padding: 14px 16px; margin: 16px 0; color: #21004B; font-size: 14px; line-height: 1.6; }
        .comment-box { background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px; padding: 14px 16px; margin: 16px 0; color: #7F1D1D; font-size: 14px; line-height: 1.6; }

        .alert { border-left: 4px solid; border-radius: 8px; padding: 12px 16px; margin: 0 0 20px; font-size: 14px; line-height: 1.5; }
        .alert-danger { background: #FEF2F2; border-color: #DC2626; color: #991B1B; }
        .alert-warning { background: #FEFCE8; border-color: #CA8A04; color: #854D0E; }
        .alert-orange { background: #FFF7ED; border-color: #EA580C; color: #9A3412; }
        .alert-info { background: #F5EEFB; border-color: #5B1690; color: #21004B; }
        .alert-success { background: #F0FDF4; border-color: #16A34A; color: #166534; }

        .code-box { display: inline-block; background: #F5EEFB; border: 2px solid #5B1690; border-radius: 12px; padding: 16px 32px; font-size: 34px; font-weight: 700; letter-spacing: 10px; color: #21004B; }

        .muted { color: #6B7280 !important; }
        .small { font-size: 12px !important; }
        .center { text-align: center; }

        .footer { background: #F7F7FA; padding: 18px 32px; font-size: 12px; line-height: 1.5; color: #6B7280; border-top: 1px solid #E5E7EB; }
        .footer strong { color: #21004B; }

        @media only screen and (max-width: 480px) {
            .header, .body, .footer { padding-left: 20px !important; padding-right: 20px !important; }
            .info-table td:first-child { width: 42%; }
        }
    </style>
</head>
<body>
    <table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F7FA">
        <tr>
            <td align="center">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF">
                    <tr>
                        <td class="header" bgcolor="#21004B">
                            <img src="{{ $logoSrc }}" alt="Jungo Cumple" height="30" style="height:30px;width:auto;border:0;display:block;">
                            @hasSection('headline')
                                <p class="headline">@yield('headline')</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="accent" bgcolor="{{ $toneColor }}" style="background: {{ $toneColor }};">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="body">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            @hasSection('footer')
                                @yield('footer')
                            @else
                                Este correo fue generado automáticamente por <strong>Jungo Cumple</strong>. Por favor no respondas a este mensaje.
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
