<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Top Link-Vote</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=raleway:400,700|figtree:400,500,600&display=swap" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite(['resources/css/students/app.css', 'resources/js/app.js'])

    @livewireStyles
    @stack('styles')
</head>

<body>
    {{ $slot }}

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

    <script>
        AOS.init({
            duration: 800,
            once: true
        });

        document.addEventListener('livewire:init', () => {
            Livewire.on('swal', (event) => {
                const data = event[0];
                Swal.fire({
                    title: data.title,
                    text: data.text,
                    icon: data.icon,
                    confirmButtonColor: '#108500',
                });
            });
        });
    </script>

    @livewireScripts
    @stack('scripts')
</body>

</html>
