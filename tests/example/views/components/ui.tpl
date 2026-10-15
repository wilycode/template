@component button($text)
<button>{{ $text }}</button>
@endcomponent

@component card($class = '', $title = null, $subtitle = null)
<section class="card {{ $class }}">
    @if $title != null
    <div class="card-header">
        <h2>{{ $title }}</h2>
        @if $subtitle != null
        <p>{{ $subtitle }}</p>
        @endif
    </div>
    @endif

    {!! $slot !!}
</section>
@endcomponent
