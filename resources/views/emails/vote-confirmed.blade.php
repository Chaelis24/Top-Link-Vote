<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e1e1e1;
        }

        .header {
            background: #0d6efd;
            color: #ffffff;
            padding: 40px 20px;
            text-align: center;
        }

        .content {
            padding: 30px;
            color: #333;
            line-height: 1.6;
        }

        .receipt {
            background: #f8f9fa;
            border: 1px dashed #ccc;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #777;
        }

        .badge {
            background: #198754;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0;">Vote Confirmed!</h1>
            <p style="opacity: 0.8;">{{ $cycle->name }}</p>
        </div>
        <div class="content">
            <p>Hello <strong>
                    {{ $student->first_name }}
                    {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : '' }}
                    {{ $student->last_name }}{{ $student->suffix ? ' ' . $student->suffix : '' }}
                </strong>,</p>
            <p>This email confirms that your official ballot has been cast and successfully encrypted in our system.
                Your participation helps shape the future of our student body.</p>

            <div class="receipt">
                <div class="d-flex justify-content-between mb-2">
                    <strong>Status:</strong>
                    <span class="badge bg-success">Verified & Encrypted</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <strong>Student ID:</strong> <span>{{ $student->student_id }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <strong>Reference No:</strong>
                    <span class="text-accent fw-bold">{{ auth()->user()->student->vote_reference }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <strong>Timestamp:</strong>
                    <span> {{ $student->voted_at->timezone('Asia/Manila')->format('M d, Y - h:i A') }}</span>
                </div>
            </div>

            <p style="margin-top: 30px;"><em>Note: To maintain the secrecy of the ballot, individual candidate
                    selections are not listed in this receipt.</em></p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Automated Election System. This is an automated message, please do not reply.
        </div>
    </div>
</body>

</html>
