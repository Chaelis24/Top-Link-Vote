<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Top Link-Vote' }}</title>

    @vite(['resources/css/students/app.css', 'resources/js/app.js'])

    @livewireStyles
    @stack('styles')
</head>

<body>
    {{ $slot }}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @livewireScripts
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
    @stack('scripts')
    <script src="https://openfpcdn.io/fingerprintjs/v4" defer></script>
    <script>
        function getFallbackDeviceToken() {
            let token = localStorage.getItem('_device_token');
            if (!token) {
                token = 'fp-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('_device_token', token);
            }
            return token;
        }

        function setDeviceToken(token) {
            const input = document.getElementById('device_token');
            if (input) {
                input.value = token;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        function secureSyncDeviceToken(retries) {
            retries = retries || 0;
            if (window.FingerprintJS) {
                FingerprintJS.load()
                    .then(fp => fp.get())
                    .then(result => setDeviceToken(result.visitorId))
                    .catch(function() {
                        setDeviceToken(getFallbackDeviceToken());
                    });
            } else if (retries < 15) {
                setTimeout(function() { secureSyncDeviceToken(retries + 1); }, 300);
            } else {
                setDeviceToken(getFallbackDeviceToken());
            }
        }

        document.addEventListener('livewire:navigated', function() {
            setTimeout(function() { secureSyncDeviceToken(); }, 300);
        });

        window.addEventListener('DOMContentLoaded', function() {
            secureSyncDeviceToken();
            setTimeout(function() { secureSyncDeviceToken(); }, 500);
        });

        document.addEventListener('livewire:update', function() {
            secureSyncDeviceToken();
        });
    </script>
</body>

</html>
