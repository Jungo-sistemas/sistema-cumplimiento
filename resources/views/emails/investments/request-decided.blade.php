@extends('emails.layouts.jungo')

@section('title')
{{ $approved ? 'Solicitud aprobada' : 'Solicitud rechazada' }}
@endsection
@section('headline')
{{ $approved ? '✓ Solicitud de inversión aprobada' : '✗ Solicitud de inversión rechazada' }}
@endsection
@section('tone', $approved ? 'success' : 'danger')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    @if($approved)
        <p>La solicitud de inversión fue <strong>aprobada</strong> por todas las personas del flujo.</p>
    @else
        <p>La solicitud de inversión fue <strong>rechazada</strong>{{ $rejectedBy ? ' por ' . $rejectedBy->name : '' }}.</p>
    @endif

    <table class="info-table">
        <tr><td>Motivo</td><td><strong>{{ $investmentRequest->concept }}</strong></td></tr>
        <tr><td>Empresa</td><td>{{ $investmentRequest->displayCompanyName() ?? '—' }}</td></tr>
        <tr><td>Monto</td><td>${{ number_format($investmentRequest->amount) }}</td></tr>
        <tr><td>Solicitante</td><td>{{ $investmentRequest->requester->name ?? '—' }}</td></tr>
        <tr>
            <td>Flujo</td>
            <td>
                @foreach($approvals as $a)
                    {{ $a->sequence_order }}. {{ $a->user?->name ?? '—' }} — {{ $a->statusLabel() }}<br>
                @endforeach
            </td>
        </tr>
    </table>

    @if(! $approved && $comments)
        <p><strong>Motivo del rechazo:</strong></p>
        <div class="comment-box">{{ $comments }}</div>
    @endif

    <a href="{{ route('investment-requests.show', $investmentRequest) }}" class="btn">Ver solicitud</a>
@endsection
