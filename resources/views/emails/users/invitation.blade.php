@extends('emails.layouts.jungo')

@section('title', 'Invitación a Jungo Cumple')
@section('headline', 'Te damos la bienvenida a Jungo Cumple')
@section('tone', 'brand')

@section('content')
    <h2>Has sido invitado a Jungo Cumple</h2>

    <p>Hola, <strong>{{ $user->name }}</strong>.</p>

    <p>Un administrador te ha registrado en la plataforma Jungo Cumple con los siguientes datos:</p>

    <table class="info-table">
        <tr><td>Empresa</td><td><strong>{{ $user->company->name ?? 'N/A' }}</strong></td></tr>
        <tr><td>Rol</td><td><strong>{{ $user->role->name ?? 'N/A' }}</strong></td></tr>
        <tr><td>Correo</td><td><strong>{{ $user->email }}</strong></td></tr>
    </table>

    <p>Da clic en el botón para activar tu cuenta y crear tu contraseña:</p>

    <p class="center" style="margin: 24px 0;">
        <a href="{{ route('invitation.accept', $user->invite_token) }}" class="btn">Activar cuenta</a>
    </p>

    <div class="alert alert-warning">
        <strong>Importante:</strong> este enlace expira el
        <strong>{{ optional($user->invite_expires_at)->format('d/m/Y \a \l\a\s H:i') }}</strong>.
        Si no activas tu cuenta antes de esa fecha, solicita una nueva invitación al administrador.
    </div>

    <p class="muted" style="margin-bottom: 0;">
        Si no esperabas este correo o crees que fue un error, puedes ignorarlo sin problema.
    </p>
@endsection
