@php $regulation = $audit->regulation; @endphp
@extends('emails.layouts.jungo')

@section('title', 'Este mes te toca una auditoría')
@section('headline')
Auditoría programada para {{ $audit->plannedMonthLabel() }}
@endsection
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>Este mes te toca una auditoría del siguiente proceso:</p>

    <table class="info-table">
        <tr><td>Proceso</td><td><strong>{{ $regulation->code ? $regulation->code . ' — ' : '' }}{{ $regulation->name }}</strong></td></tr>
        <tr><td>Empresa</td><td>{{ $audit->company?->procesos_name ?? $audit->company?->name ?? '—' }}</td></tr>
        <tr><td>Mes</td><td>{{ ucfirst($audit->plannedMonthLabel()) }}</td></tr>
        <tr><td>Auditor</td><td>{{ $audit->auditor?->name ?? 'Por asignar' }}</td></tr>
    </table>

    <div class="note">
        El auditor se pondrá de acuerdo contigo para el día y la hora; la auditoría es en vivo por Teams.
        Revisa el checklist del proceso y ten listas tus evidencias.
    </div>

    <a href="{{ route('mi-espacio.audits') }}" class="btn">Ver mis auditorías</a>
@endsection
