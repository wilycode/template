@use("components.ui", "ui")
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'My application' }}</title>
</head>
<body>
<header>

</header>

<main>
    @yield('content')
</main>
@ui.button("OK")
<footer>
    <small>&copy; {{ date('Y') }} – sample application</small>
</footer>
</body>
</html>