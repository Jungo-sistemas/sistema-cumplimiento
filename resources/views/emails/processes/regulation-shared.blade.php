@extends('emails.layouts.jungo')

@section('title', 'Nuevo procedimiento disponible')
@section('headline', 'Nuevo procedimiento disponible para ti')
@section('tone', 'brand')

@section('content')
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
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
    </table>

    <a href="{{ route('processes.view-track', [$regulation, $share->token]) }}" class="btn">
        Ver procedimiento
    </a>

    <p class="muted small" style="margin-top: 16px; word-break: break-all;">
        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
        {{ route('processes.view-track', [$regulation, $share->token]) }}
    </p>
@endsection
