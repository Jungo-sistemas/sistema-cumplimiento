@extends('emails.layouts.jungo')

@section('title', 'Aprobación requerida')
@section('headline', 'Solicitud de inversión pendiente de tu aprobación')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>
        Es tu turno de revisar una solicitud de inversión.
        @if($position && $total > 1)
            Eres el aprobador <strong>{{ $position }} de {{ $total }}</strong>.
        @endif
    </p>

    <table class="info-table">
        <tr><td>Motivo</td><td><strong>{{ $investmentRequest->concept }}</strong></td></tr>
        <tr><td>Empresa</td><td>{{ $investmentRequest->displayCompanyName() ?? '—' }}</td></tr>
        <tr><td>Monto</td><td>${{ number_format($investmentRequest->amount) }}</td></tr>
        <tr><td>Solicitante</td><td>{{ $investmentRequest->requester->name ?? '—' }}</td></tr>
        <tr><td>Fecha límite</td><td>{{ $investmentRequest->deadline_at?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td>Anexo 8</td><td>{{ $investmentRequest->original_name ?? '—' }}</td></tr>
        @if($previousApprovers->isNotEmpty())
        <tr><td>Ya aprobaron</td><td>{{ $previousApprovers->map(fn ($a) => $a->user?->name)->filter()->join(', ') }}</td></tr>
        @endif
    </table>

    <a href="{{ route('investment-requests.show', $investmentRequest) }}" class="btn">Revisar solicitud</a>
@endsection
