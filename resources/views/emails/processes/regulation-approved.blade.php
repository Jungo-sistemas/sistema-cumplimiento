@extends('emails.layouts.jungo')

@section('title', 'Documento aprobado')
@section('headline', '✓ Documento aprobado')
@section('tone', 'success')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>El siguiente documento completó su flujo de aprobación exitosamente y ya se encuentra <strong>vigente</strong> en el sistema.</p>

    <table class="info-table">
        <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
    </table>

    <a href="{{ route('processes.show', $regulation) }}" class="btn">Ver documento</a>
@endsection
