@php
    $classe = match ($orcamento->status) {
        'rascunho' => '',
        'enviado' => 'selo-azul',
        'fechado' => 'selo-ok',
        'cancelado' => 'selo-perigo',
        default => '',
    };
@endphp
<span class="selo {{ $classe }}">{{ $orcamento->rotuloTipo() }} · {{ $orcamento->rotuloStatus() }}</span>
