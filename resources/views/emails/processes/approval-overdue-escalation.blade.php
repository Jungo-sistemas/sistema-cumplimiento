@extends('emails.layouts.jungo')

@section('title', 'Aprobación sin resolver')
@section('headline', 'Aprobación sin resolver')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>
        <strong>{{ $pendingUser->name }}</strong> tiene una aprobación pendiente desde hace
        <span class="badge badge-red">{{ $daysOverdue }} días</span>
        y ya se le enviaron 3 recordatorios sin respuesta. Como administrador del módulo de
        Procesos, te avisamos para que puedas dar seguimiento manual.
    </p>

    <table class="info-table">
        <tr><td>Documento</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
        <tr><td>Aprobador pendiente</td><td>{{ $pendingUser->name }} ({{ $pendingUser->email }})</td></tr>
    </table>

    <a href="{{ route('processes.flow', $regulation) }}" class="btn">Ver flujo de aprobación</a>
@endsection
