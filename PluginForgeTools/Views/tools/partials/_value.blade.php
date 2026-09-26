{{-- Renderiza qualquer valor (escalar, lista ou objeto) de forma legível e recursiva. --}}
@if(is_array($value))
    <ul class="list-unstyled mb-0 ps-3">
        @foreach($value as $vk => $vv)
            <li>
                @if(!is_int($vk))<span class="text-muted">{{ e($vk) }}: </span>@endif
                @if(is_array($vv))
                    @include('PluginForgeTools::tools.partials._value', ['value' => $vv])
                @elseif(is_bool($vv))
                    {{ $vv ? 'true' : 'false' }}
                @elseif($vv === null)
                    —
                @else
                    {{ e((string) $vv) }}
                @endif
            </li>
        @endforeach
    </ul>
@else
    {{ e((string) $value) }}
@endif
