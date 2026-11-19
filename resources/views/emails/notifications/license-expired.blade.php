@php
    $name = $license->licensable->name ?? 'Cliente';
    $tipo = $license->licensable_type === \App\Models\Group::class ? 'grupo' : 'empresa';
@endphp
@extends('emails.layouts.jungo')

@section('title', 'Licencia vencida — acceso suspendido')
@section('headline', 'Licencia vencida — acceso suspendido')
@section('tone', 'danger')

@section('content')
    <div class="alert alert-danger">
        <strong style="color: #991B1B;">
            Venció la licencia del {{ $tipo }} «{{ $name }}» — se le quitó el acceso al sistema automáticamente.
        </strong>
    </div>

    <table class="info-table">
        <tr><td>{{ ucfirst($tipo) }}</td><td><strong>{{ $name }}</strong></td></tr>
        <tr><td>Venció</td><td><strong>{{ $license->expires_at->format('d/m/Y H:i') }}</strong></td></tr>
        <tr><td>Monto pendiente</td><td><strong>${{ number_format((float) $license->price, 2) }} MXN</strong></td></tr>
    </table>

    <p style="margin-bottom: 0;">
        En cuanto el cliente confirme el pago, activa una nueva licencia desde el panel de Superadmin para
        restaurarle el acceso.
    </p>
@endsection
