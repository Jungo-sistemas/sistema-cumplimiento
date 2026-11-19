@php $regulation = $audit->regulation; @endphp
@extends('emails.layouts.jungo')

@section('title', 'Fecha de tu auditoría')
@section('headline')
{{ $rescheduled ? 'Cambió la fecha de tu auditoría' : 'Fecha de tu auditoría' }}
@endsection
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>
        {{ $rescheduled ? 'El auditor cambió la fecha de tu auditoría. La nueva fecha es:' : 'El auditor registró la fecha acordada de tu auditoría:' }}
    </p>

    <table class="info-table">
        <tr><td>Fecha y hora</td><td><strong>{{ $audit->scheduledLabel() }}</strong></td></tr>
        <tr><td>Proceso</td><td>{{ $regulation->code ? $regulation->code . ' — ' : '' }}{{ $regulation->name }}</td></tr>
        <tr><td>Empresa</td><td>{{ $audit->company?->procesos_name ?? $audit->company?->name ?? '—' }}</td></tr>
        <tr><td>Auditor</td><td>{{ $audit->auditor?->name ?? '—' }}</td></tr>
    </table>

    <a href="{{ route('mi-espacio.audits') }}" class="btn">Ver mis auditorías</a>
@endsection
