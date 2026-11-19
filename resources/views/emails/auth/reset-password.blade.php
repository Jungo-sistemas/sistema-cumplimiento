@extends('emails.layouts.jungo')

@section('title', 'Restablecer contraseña')
@section('headline', 'Restablece tu contraseña')
@section('tone', 'brand')

@section('content')
    <p>Hola, <strong>{{ $user->name }}</strong>.</p>

    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en Jungo Cumple. Da clic en el botón para crear una nueva contraseña:</p>

    <p class="center" style="margin: 24px 0;">
        <a href="{{ $url }}" class="btn">Restablecer contraseña</a>
    </p>

    <div class="alert alert-danger">
        <strong>Atención:</strong> este enlace es válido por
        <strong>{{ $expiryMinutes }} minutos</strong> y solo puede usarse una vez.
    </div>

    <p>Si no solicitaste restablecer tu contraseña, ignora este correo. Tu contraseña no cambiará.</p>

    <p class="muted small" style="margin-bottom: 0;">
        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
        <span style="word-break: break-all; color: #5B1690;">{{ $url }}</span>
    </p>
@endsection
