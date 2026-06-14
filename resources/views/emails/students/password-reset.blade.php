<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
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
            <h1 style="margin:0;">Password Reset Request</h1>
        </div>
        <div class="content">
            <p>
                Hello <strong>
                    {{ $student->first_name }}
                    {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : '' }}
                    {{ $student->last_name }}{{ $student->suffix ? ' ' . $student->suffix : '' }}</strong>,
            </p>

            <p>
                We received a request to reset the password for your election account. You can reset your password by
                clicking the button below:
            </p>

            <div style="text-align: center; margin: 35px 0;">
                <a href="{{ $url }}"
                    style="background-color: #10b981; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;">
                    Reset My Password
                </a>
            </div>

            <p>
                <strong>Note:</strong> This password reset link is valid for <strong>60 minutes</strong> only.
                If you did not request a password reset, no further action is required and you can safely ignore this
                email.
            </p>
        </div>
        <div class="footer">
            <span style="vertical-align: middle; margin-right: 5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#0d6efd" viewBox="0 0 16 16"
                    style="display: inline-block;">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                    <path
                        d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0" />
                </svg>
            </span> {{ date('Y') }} This is an automated message, please do not reply.
        </div>
    </div>
</body>

</html>
