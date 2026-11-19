@extends('emails.layouts.jungo')

@section('title', 'Solicitud de acceso a reglamento')
@section('headline', 'Solicitud de acceso a reglamento')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p><strong>{{ $requester->name }}</strong> quiere poder editar el siguiente reglamento, pero
       no está asignado como responsable. Si corresponde, puedes agregarlo como responsable desde
       "Info básica" para que pueda editarlo.</p>

    <table class="info-table">
        <tr><td>Reglamento</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
        <tr><td>Solicitado por</td><td>{{ $requester->name }} ({{ $requester->email }})</td></tr>
    </table>

    <a href="{{ route('processes.editBasic', $regulation) }}?highlight={{ $requester->id }}#responsables" class="btn">Dar acceso a {{ $requester->name }}</a>
@endsection
