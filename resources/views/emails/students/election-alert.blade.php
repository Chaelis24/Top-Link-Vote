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
            color: #ffffff;
            padding: 40px 20px;
            text-align: center;
        }

        .content {
            padding: 30px;
            color: #333;
            line-height: 1.6;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #068a08;
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

        .button {
            display: inline-block;
            background: #198754;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header" style="background: linear-gradient(115deg, #0dff00, #068a08, #010d05);">
            <h1 style="margin:0;">{{ $type === 'started' ? 'Voting is Open!' : 'Election Reminder' }}</h1>
            <p style="opacity: 0.8;">{{ $cycle->name ?? 'Student Election' }}</p>
        </div>

        <div class="content">
            <p>Hello <strong>
                    {{ $student->first_name ?? 'Student' }}
                    {{ $student->last_name ?? '' }}
                </strong>,</p>

            @if ($type === 'started')
                <p>The official voting period has officially commenced. We invite you to exercise your right to vote and
                    help select our next student leaders.</p>
            @else
                <p>This is a reminder that you haven't cast your vote yet. Don't miss the chance to participate in this
                    election cycle.</p>
            @endif

            <div class="info-box">
                <div style="margin-bottom: 8px;"><strong>Student ID:</strong> {{ $student->student_id ?? 'N/A' }}</div>
                <div style="margin-bottom: 8px;"><strong>Ends on:</strong>
                    {{ \Carbon\Carbon::parse($cycle->voting_end)->format('M d, Y - h:i A') }}</div>
            </div>

            <div style="text-align: center;">
                <a href="{{ url('/') }}" class="button" style="color: #ffffff;">CAST YOUR VOTE NOW</a>
            </div>
        </div>

        <div class="footer">
            {{ date('Y') }} Top Link-Vote System. This is an automated message.
        </div>
    </div>
</body>

</html>
