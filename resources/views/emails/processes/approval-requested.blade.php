@extends('emails.layouts.jungo')

@section('title', 'Aprobación requerida')
@section('headline', 'Se requiere tu aprobación')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>Se ha creado un nuevo documento que requiere tu aprobación. A continuación los detalles:</p>

    <table class="info-table">
        <tr><td>Nombre</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Tipo</td><td>{{ $regulation->document_type ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
        <tr><td>Elaborado por</td><td>{{ $elaboradoPor ?? '—' }}</td></tr>
        <tr>
            <td>Nivel de impacto</td>
            <td>
                @php
                    $level = $regulation->impact_level;
                    $badge = match($level) {
                        'alto' => 'badge-red',
                        'medio_alto' => 'badge-yellow',
                        'medio' => 'badge-blue',
                        default => 'badge-gray',
                    };
                @endphp
                <span class="badge {{ $badge }}">{{ $regulation->impactLevelLabel() }}</span>
            </td>
        </tr>
    </table>

    @if($previousApprovers->isNotEmpty())
        <p style="margin-bottom: 8px;"><strong>Ya revisado y aprobado por:</strong></p>
        <table class="approvers-table">
            <tr>
                <td class="head">Puesto</td>
                <td class="head">Aprobador</td>
                <td class="head">Fecha</td>
            </tr>
            @foreach($previousApprovers as $prev)
                <tr>
                    <td>{{ $prev->jobPosition->name ?? '—' }}</td>
                    <td>{{ $prev->user->name ?? '—' }}</td>
                    <td>{{ $prev->decided_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p>Ingresa al sistema para revisar el documento completo y emitir tu decisión.</p>

    <a href="{{ route('processes.show', $regulation) }}" class="btn">Ver documento</a>
@endsection
