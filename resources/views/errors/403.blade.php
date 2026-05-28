<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body class="bg-slate-900 flex items-center justify-center min-h-screen text-white font-sans">

    <div class="glass-card max-w-md w-full mx-4 p-8 rounded-3xl shadow-2xl text-center">
        <div class="icon-box bg-red-900/30 p-4 rounded-full w-20 h-20 mx-auto mb-6 flex items-center justify-center">
            <i class="bi bi-exclamation-triangle-fill text-red-500 text-4xl"></i>
        </div>

        <h1 class="text-4xl font-bold mb-2 text-white">403</h1>
        <h2 class="text-xl font-semibold text-slate-300 mb-4">Access Denied</h2>

        <p class="text-slate-400 mb-8 text-sm">
            {{ $exception->getMessage() ?: 'You do not have the necessary permissions to view this page.' }}
        </p>

        <button type="button" onclick="window.history.back()"
            class="btn btn-glow w-full py-3 rounded-lg font-bold text-white transition cursor-pointer">
            Return to Previous Page
        </button>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</body>

</html>
