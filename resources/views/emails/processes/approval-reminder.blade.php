@extends('emails.layouts.jungo')

@section('title', 'Recordatorio de aprobación pendiente')
@section('headline', 'Recordatorio de aprobación pendiente')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>
        Tienes una aprobación pendiente desde hace
        <span class="badge badge-red">{{ $daysOverdue }} día{{ $daysOverdue === 1 ? '' : 's' }}</span>
        sin resolver. Este es el recordatorio {{ $reminderNumber }} de 3.
    </p>

    <table class="info-table">
        <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
    </table>

    @if($reminderNumber >= 3)
        <p>Este es el último recordatorio automático — si no se resuelve, se avisará a los administradores del módulo de Procesos para dar seguimiento.</p>
    @endif

    <p>Ingresa al sistema para revisar el documento completo y emitir tu decisión.</p>

    <a href="{{ route('processes.show', $regulation) }}" class="btn">Ver documento</a>
@endsection
