@extends('emails.layouts.jungo')

@section('title', 'Documento rechazado')
@section('headline', '✗ Documento rechazado')
@section('tone', 'danger')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>Tu documento ha sido <strong>rechazado</strong>. Puedes revisarlo, corregirlo y re-enviarlo a aprobación desde el sistema.</p>

    <table class="info-table">
        <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
        @if($rejectedBy)
        <tr><td>Rechazado por</td><td>{{ $rejectedBy->name }}</td></tr>
        @endif
    </table>

    @if($comments)
    <p><strong>Motivo del rechazo:</strong></p>
    <div class="comment-box">{{ $comments }}</div>
    @endif

    <a href="{{ route('processes.show', $regulation) }}" class="btn">Ver documento</a>
@endsection
