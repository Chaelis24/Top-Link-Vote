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

    @vite(['resources/css/students/app.css', 'resources/js/app.js'])

    @livewireStyles
    @stack('styles')
</head>

<body>
    {{ $slot }}

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @livewireScripts

    <script>
        AOS.init({
            duration: 800,
            once: true
        });

        document.addEventListener('livewire:init', () => {
            const fireSwal = (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;

                if (!data) return;

                if (data.toast) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                    });
                    Toast.fire({
                        icon: data.icon || 'success',
                        title: data.title || data.text
                    });
                } else {
                    Swal.fire({
                        title: data.title || (data.icon === 'error' ? 'Oops!' : 'Notification'),
                        text: data.text,
                        icon: data.icon || 'info',
                        timer: data.timer || null,
                        showConfirmButton: data.showConfirmButton !== undefined ? data
                            .showConfirmButton : true,
                        confirmButtonColor: '#108500',
                    });
                }
            };

            Livewire.on('swal', fireSwal);
            Livewire.on('swal:modal', fireSwal);
            Livewire.on('swal:toast', (data) => {
                const d = Array.isArray(data) ? data[0] : data;
                d.toast = true;
                fireSwal(d);
            });

            Livewire.on('auth-failed', () => {
                console.log('Login attempt failed.');
            });
        });

        @if (session()->has('status'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "{{ session('status') }}",
                confirmButtonColor: '#108500',
            });
        @endif
    </script>

    @stack('scripts')
</body>

</html>
