@php
    [$alertClass, $alertColor, $alertText, $tone] = match (true) {
        $daysUntilDue <= 1  => ['alert-danger', '#991B1B', '¡Alerta! Los siguientes requisitos vencen MAÑANA.', 'danger'],
        $daysUntilDue <= 7  => ['alert-orange', '#9A3412', "Los siguientes requisitos vencen en {$daysUntilDue} días.", 'warning'],
        $daysUntilDue <= 30 => ['alert-warning', '#854D0E', 'Aviso: los siguientes requisitos vencen en menos de 30 días.', 'warning'],
        default             => ['alert-info', '#21004B', 'Recordatorio: los siguientes requisitos vencen en menos de 60 días.', 'brand'],
    };
@endphp
@extends('emails.layouts.jungo')

@section('title', 'Requisitos próximos a vencer')
@section('headline', 'Requisitos próximos a vencer')
@section('tone', $tone)

@section('content')
    <div class="alert {{ $alertClass }}">
        <strong style="color: {{ $alertColor }};">{{ $alertText }}</strong>
    </div>

    <p>Hola, <strong>{{ $notifiable->name }}</strong>.</p>
    <p>Tienes <strong>{{ $requirements->count() }}</strong> requisito(s) próximos a vencer que requieren tu atención:</p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Activo</th>
                <th>Requisito</th>
                <th>Fecha de vencimiento</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requirements as $req)
                @php
                    $daysLeft = now()->diffInDays($req->due_date, false);
                    $rowColor = $daysLeft <= 1 ? '#FEF2F2' : ($daysLeft <= 7 ? '#FFF7ED' : '#FFFFFF');
                @endphp
                <tr style="background: {{ $rowColor }};">
                    <td>{{ $req->asset->name ?? '—' }}</td>
                    <td>{{ $req->template->name ?? $req->type }}</td>
                    <td>{{ $req->due_date->format('d/m/Y') }}</td>
                    <td>{{ $req->status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-bottom: 0;">
        Ingresa al sistema para gestionar estos requisitos antes de su fecha de vencimiento.
    </p>
@endsection
