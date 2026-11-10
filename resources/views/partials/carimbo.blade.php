@use('App\Support\Formato')
{{-- $quando: Carbon|null, $rotulo: texto. Avisa quando os dados estão velhos (mais de 3 dias). --}}
@php $velho = $quando && $quando->lt(now()->subDays(3)); @endphp
<div class="carimbo {{ ($velho || ! $quando) ? 'velho' : '' }}">
    @if($quando)
        {{ $rotulo }} atualizados em {{ Formato::data($quando, true) }}@if($velho) — dados com mais de 3 dias @endif
    @else
        {{ $rotulo }}: ainda não importados
    @endif
</div>
