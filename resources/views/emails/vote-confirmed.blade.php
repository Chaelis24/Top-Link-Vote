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
        <div class="header" style="background: linear-gradient(115deg, #0dff00, #068a08, #010d05);">
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
                <div class="d-flex justify-content-between mb-2">
                    <strong>Student ID:</strong> <span>{{ $student->student_id }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <strong>Reference No:</strong>
                    <span class="text-accent fw-bold">{{ auth()->user()->student->vote_reference }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <strong>Timestamp:</strong>
                    <span> {{ $student->voted_at->timezone('Asia/Manila')->format('M d, Y - h:i A') }}</span>
                </div>
            </div>

            <p style="margin-top: 30px;"><em>Note: To maintain the secrecy of the ballot, individual candidate
                    selections are not listed in this receipt.</em></p>
        </div>
        <div class="footer">
            <span style="vertical-align: middle; margin-right: 5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#0d6efd" viewBox="0 0 16 16"
                    style="display: inline-block;">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                    <path
                        d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0" />
                </svg>
            </span> {{ date('Y') }} Top Link-Vote System. This is an automated message, please
            do not reply.
        </div>
    </div>
</body>

</html>
