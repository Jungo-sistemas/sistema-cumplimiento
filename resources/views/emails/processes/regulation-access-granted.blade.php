@extends('emails.layouts.jungo')

@section('title', 'Acceso otorgado a reglamento')
@section('headline', 'Acceso otorgado')
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>Ya se te dio acceso para editar el siguiente reglamento:</p>

    <table class="info-table">
        <tr><td>Reglamento</td><td><strong>{{ $regulation->name }}</strong></td></tr>
        <tr><td>Código</td><td>{{ $regulation->code ?? '—' }}</td></tr>
        <tr><td>Empresa</td><td>{{ $regulation->company->procesos_name ?? '—' }}</td></tr>
    </table>

    <a href="{{ route('processes.show', $regulation) }}" class="btn">Ver reglamento</a>
@endsection
