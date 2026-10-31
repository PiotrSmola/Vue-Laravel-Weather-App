<x-mail::message>
# {{ $event->title }}

@if ($userName)
Cześć {{ $userName }},
@endif

{{ $event->message }}

<x-mail::table>
| Szczegóły | |
|:--|:--|
| Miasto | **{{ $event->city?->name }}{{ $event->city?->country ? ', ' . $event->city->country : '' }}** |
| Alert | {{ $typeLabel }} |
@if ($conditionLabel)
| Zjawisko | **{{ $conditionLabel }}** |
@endif
@if ($value !== null)
| Wartość | **{{ $value }}** |
@endif
@if ($threshold !== null)
| Próg | {{ $threshold }} |
@endif
| Czas | {{ $event->triggered_at->copy()->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }} |
</x-mail::table>

<x-mail::button :url="$cityUrl">
Zobacz pogodę w mieście {{ $event->city?->name }}
</x-mail::button>

Kolejne powiadomienie z tej reguły zostanie wysłane najwcześniej po upływie ustawionego odstępu.
Reguły alertów możesz zmienić lub wyłączyć na stronie [Alerty]({{ $alertsUrl }}).

Pozdrawiamy,<br>
Poland&World Weather
</x-mail::message>
