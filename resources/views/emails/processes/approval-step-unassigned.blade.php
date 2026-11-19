@extends('emails.layouts.jungo')

@section('title', 'Flujo de aprobación detenido')
@section('headline', 'Flujo de aprobación detenido')
@section('tone', 'danger')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>El paso {{ $step }} del flujo de aprobación de este documento no tiene a nadie que pueda
       decidirlo: {{ count($missingPositions) === 1 ? 'el puesto' : 'los puestos' }}
       <strong>{{ implode(', ', $missingPositions) }}</strong>
       no {{ count($missingPositions) === 1 ? 'tiene' : 'tienen' }} ningún usuario asignado
       {{ count($missingPositions) === 1 ? 'en este' : 'en esta empresa' }}. El documento se
       quedará en "En autorización" indefinidamente hasta que se asigne a alguien a ese puesto
       y el flujo se reenvíe.</p>

    <table class="info-table">
        <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
        <tr><td>Paso detenido</td><td>{{ $step }}</td></tr>
    </table>

    <a href="{{ route('processes.flow', $regulation) }}" class="btn">Asignar el puesto faltante</a>
@endsection
