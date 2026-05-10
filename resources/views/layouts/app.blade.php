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

    @vite(['resources/css/students/app.css', 'resources/css/students/students.css', 'resources/css/students/student-sidebar.css', 'resources/js/app.js'])

    {{-- Livewire Styles --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    @livewireStyles
    @stack('styles')
</head>

<body>

    {{ $slot }}

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @livewireScripts
    @stack('scripts')
    <script>
        document.addEventListener("livewire:init", () => {
            Livewire.on("swal", (data) => {
                const options = data[0];

                Swal.fire({
                    title: options.title || 'Notification',
                    text: options.text || '',
                    icon: options.icon || 'info',
                    confirmButtonColor: options.icon === 'error' ? '#ef4444' : '#10b981',
                    confirmButtonText: options.confirmButtonText || "Understood",
                });
            });
        });

        document.addEventListener('livewire:init', () => {
            Livewire.on('close-modal', () => {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modalElement => {
                    const modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });

                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());

                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });
        });
    </script>
    @auth
        <script type="module">
            document.addEventListener('DOMContentLoaded', function() {
                Echo.private(`user.{{ auth()->id() }}`)
                    .listen('.account.duplicate-login', (e) => {
                        Swal.fire({
                            title: "Security Alert",
                            html: "Someone else just logged into your account. You will be logged out in <b></b> seconds.",
                            icon: "warning",
                            timer: 5000,
                            timerProgressBar: true,
                            confirmButtonText: "Log out now",
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                const b = Swal.getHtmlContainer().querySelector('b');
                                const timerInterval = setInterval(() => {
                                    const secondsLeft = Math.ceil(Swal.getTimerLeft() / 1000);
                                    b.textContent = secondsLeft;
                                }, 100);
                            }
                        }).then((result) => {
                            window.location.reload();
                        });
                    });
            });
        </script>
    @endauth
</body>

</html>
