@extends('emails.layouts.jungo')

@section('title', 'Código de verificación')
@section('headline', 'Código de verificación')
@section('tone', 'brand')

@section('content')
    <p>Hola, <strong>{{ $user->name }}</strong>.</p>

    <p>Usa el siguiente código para completar tu inicio de sesión. Expira en <strong>10 minutos</strong>.</p>

    <p class="center" style="margin: 28px 0;">
        <span class="code-box">{{ $code }}</span>
    </p>

    <div class="alert alert-danger" style="margin-bottom: 0;">
        <strong>Importante:</strong> si no fuiste tú quien inició sesión, cambia tu contraseña de inmediato.
    </div>
@endsection
