{{-- Correo genérico de auditorías: $headline, $intro, $rows (etiqueta => valor), $note (opcional),
     $noteTone ('blue' | 'red' | 'green'), $buttonUrl y $buttonLabel (opcionales). --}}
@php
    $tones = [
        'blue'  => ['#F5EEFB', '#EADCF7', '#21004B'],
        'red'   => ['#fef2f2', '#fecaca', '#7f1d1d'],
        'green' => ['#f0fdf4', '#bbf7d0', '#14532d'],
    ];
    [$noteBg, $noteBorder, $noteText] = $tones[$noteTone ?? 'blue'] ?? $tones['blue'];
@endphp
@extends('emails.layouts.jungo')

@section('title')
{{ $headline }}
@endsection
@section('headline')
{{ $headline }}
@endsection
@section('tone', 'brand')

@section('content')
    <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>
    <p>{{ $intro }}</p>

    <table class="info-table">
        @foreach($rows as $label => $value)
            @if(filled($value))
                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endif
        @endforeach
    </table>

    @if(! empty($note))
        <div class="note" style="background: {{ $noteBg }}; border: 1px solid {{ $noteBorder }}; color: {{ $noteText }}; white-space: pre-line;">{{ $note }}</div>
    @endif

    @if(! empty($buttonUrl))
        <a href="{{ $buttonUrl }}" class="btn">{{ $buttonLabel ?? 'Ver en el sistema' }}</a>
    @endif
@endsection
