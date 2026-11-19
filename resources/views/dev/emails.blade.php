{{-- /dev/correos — solo desarrollo (ver EmailPreviewController). Página suelta, sin el layout
     de la plataforma ni assets de Vite: estilos en línea. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Correos (dev)</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', 'Segoe UI', Arial, sans-serif; background: #F7F7FA; color: #1F2937; display: flex; height: 100vh; }
        nav { width: 280px; flex-shrink: 0; background: #fff; border-right: 1px solid #E5E7EB; overflow-y: auto; padding: 16px 12px; }
        nav h1 { font-size: 15px; color: #21004B; margin: 0 8px 4px; }
        nav .dev { font-size: 11px; color: #9CA3AF; margin: 0 8px 12px; }
        nav p { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9CA3AF; margin: 14px 8px 4px; }
        nav a { display: block; padding: 6px 8px; border-radius: 8px; font-size: 13px; color: #374151; text-decoration: none; }
        nav a:hover { background: #F3F4F6; }
        nav a.on { background: #F5EEFB; color: #5B1690; font-weight: 600; }
        main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        header { display: flex; align-items: center; gap: 12px; padding: 12px 20px; background: #fff; border-bottom: 1px solid #E5E7EB; }
        header .subject { flex: 1; min-width: 0; }
        header small { color: #9CA3AF; font-size: 11px; }
        header div.s { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        header button, header a { font: inherit; font-size: 12px; font-weight: 600; border: 1px solid #E5E7EB; background: #fff; color: #374151; border-radius: 8px; padding: 6px 12px; cursor: pointer; text-decoration: none; }
        header button.on { background: #5B1690; border-color: #5B1690; color: #fff; }
        .frame { flex: 1; display: flex; justify-content: center; padding: 16px; overflow: hidden; }
        iframe { width: 100%; height: 100%; border: 1px solid #E5E7EB; border-radius: 10px; background: #fff; }
        iframe.mobile { max-width: 390px; }
    </style>
</head>
<body>
    <nav>
        <h1>Correos</h1>
        <div class="dev">Solo desarrollo · datos de ejemplo</div>
        @foreach($groups as $group => $emails)
            <p>{{ $group }}</p>
            @foreach($emails as $email)
                <a href="{{ route('dev.emails', ['correo' => $email['key']]) }}" class="{{ $email['key'] === $selected['key'] ? 'on' : '' }}">{{ $email['label'] }}</a>
            @endforeach
        @endforeach
    </nav>
    <main>
        <header>
            <div class="subject">
                <small>{{ $selected['group'] }} · Asunto</small>
                <div class="s" title="{{ $subject }}">{{ $subject }}</div>
            </div>
            <button type="button" id="desk" class="on">Computadora</button>
            <button type="button" id="cel">Celular</button>
            <a href="{{ route('dev.emails.show', $selected['key']) }}" target="_blank">Abrir aparte</a>
        </header>
        <div class="frame">
            <iframe id="preview" src="{{ route('dev.emails.show', $selected['key']) }}" title="{{ $selected['label'] }}"></iframe>
        </div>
    </main>
    <script>
        const frame = document.getElementById('preview'), desk = document.getElementById('desk'), cel = document.getElementById('cel');
        desk.onclick = () => { frame.classList.remove('mobile'); desk.classList.add('on'); cel.classList.remove('on'); };
        cel.onclick = () => { frame.classList.add('mobile'); cel.classList.add('on'); desk.classList.remove('on'); };
    </script>
</body>
</html>
