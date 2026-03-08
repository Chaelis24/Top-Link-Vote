<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <title>{{ $title ?? 'Top Link-Vote' }}</title>

    {{-- Bootstrap & Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    {{-- AOS Animations --}}
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/css/admin.css', 'resources/css/students.css', 'resources/js/app.js'])

    {{-- Livewire Styles --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    @livewireStyles
    @stack('styles')
</head>

<body>
    <div class="bg-animated"></div>
    <div class="particles" id="particles"></div>

    {{-- DITO PAPASOK ANG VOLT COMPONENT --}}
    {{ $slot }}

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        window.addEventListener('swal:modal', event => {
            Swal.fire({
                title: `<span style="color: #ffffff; font-family: sans-serif;">${event.detail[0].title}</span>`,
                text: event.detail[0].text,
                icon: event.detail[0].type,
                background: '#1a222c',
                color: '#cbd5e0',
                confirmButtonText: 'Understood',
                confirmButtonColor: '#28a745',
                borderRadius: '15px',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            });
        });
    </script>
    {{-- Livewire Scripts --}}
    @livewireScripts
    @stack('scripts')
</body>

</html>
