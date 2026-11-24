@php $regulation = $audit->regulation; @endphp
@extends('emails.layouts.jungo')

@section('title', 'Tienes una auditoría')
@section('headline')
Tienes una auditoría el {{ $audit->scheduledLabel() }}
@endsection
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>El auditor programó una auditoría del siguiente proceso:</p>

    <table class="info-table">
        <tr><td>Fecha y hora</td><td><strong>{{ $audit->scheduledLabel() }}</strong></td></tr>
        <tr><td>Proceso</td><td>{{ $regulation->code ? $regulation->code . ' — ' : '' }}{{ $regulation->name }}</td></tr>
        <tr><td>Empresa</td><td>{{ $audit->company?->procesos_name ?? $audit->company?->name ?? '—' }}</td></tr>
        <tr><td>Auditor</td><td>{{ $audit->auditor?->name ?? 'Por asignar' }}</td></tr>
    </table>

    <div class="note">
        La auditoría es en vivo por Teams. Revisa el checklist del proceso y ten listas tus evidencias.
    </div>

    <a href="{{ route('mi-espacio.audits') }}" class="btn">Ver mis auditorías</a>
@endsection
