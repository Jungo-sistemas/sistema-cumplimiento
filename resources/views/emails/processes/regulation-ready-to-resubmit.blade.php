@extends('emails.layouts.jungo')

@section('title', 'Documento corregido, listo para reenviar')
@section('headline', 'Documento corregido')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>Un documento que había sido <strong>rechazado</strong> fue editado y corregido. Su flujo de
       aprobación sigue detenido — recuerda que, tras un rechazo, el flujo debe reiniciarse desde
       el primer paso. Como administrador, puedes reenviarlo a aprobación cuando confirmes que la
       corrección es correcta.</p>

    <table class="info-table">
        <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
    </table>

    <a href="{{ route('processes.flow', $regulation) }}" class="btn">Revisar y reenviar a aprobación</a>
@endsection
