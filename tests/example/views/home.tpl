@extends('layouts.main')

@section('content')
    @set('themes', ['light', 'dark'])
    @share('pageSubtitle', 'Named arguments demo')

    <h1>Welcome, {{ $name }}!</h1>
    <p>{{ $title | upper }}</p>

    @ui.card('demo-card', title: 'Menu details', subtitle: $pageSubtitle)
        <ul>
            @foreach ($themes as $theme)
                <li>{{ $theme }}</li>
            @endforeach
        </ul>
    @endui.card
@endsection
