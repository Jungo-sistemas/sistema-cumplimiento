@php
    $name = $license->licensable->name ?? 'Cliente';
    $tipo = $license->licensable_type === \App\Models\Group::class ? 'grupo' : 'empresa';
    $urgent = $daysUntilExpiration <= 1;
@endphp
@extends('emails.layouts.jungo')

@section('title', 'Licencia próxima a vencer')
@section('headline', 'Licencia próxima a vencer')
@section('tone', $urgent ? 'danger' : 'warning')

@section('content')
    <div class="alert {{ $urgent ? 'alert-danger' : 'alert-orange' }}">
        <strong style="color: {{ $urgent ? '#991B1B' : '#9A3412' }};">
            @if($urgent)
                La licencia del {{ $tipo }} «{{ $name }}» vence MAÑANA.
            @else
                La licencia del {{ $tipo }} «{{ $name }}» vence en {{ $daysUntilExpiration }} días.
            @endif
        </strong>
    </div>

    <table class="info-table">
        <tr><td>{{ ucfirst($tipo) }}</td><td><strong>{{ $name }}</strong></td></tr>
        <tr><td>Vence</td><td><strong>{{ $license->expires_at->format('d/m/Y H:i') }}</strong></td></tr>
        <tr><td>Incluye Procesos</td><td>{{ $license->includes_procesos ? 'Sí' : 'No' }}</td></tr>
        <tr><td>Monto a cobrar</td><td><strong>${{ number_format((float) $license->price, 2) }} MXN</strong></td></tr>
    </table>

    <p style="margin-bottom: 0;">
        Da seguimiento al pago con el cliente. Si no se activa una nueva licencia antes de la fecha de vencimiento,
        el sistema le quitará el acceso automáticamente.
    </p>
@endsection
