{{-- resources/views/emails/password-reset.blade.php --}}
<!DOCTYPE html>
<html>

<body
    style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; padding: 20px; margin: 0;">
    <div
        style="max-width: 500px; margin: 20px auto; background: #ffffff; padding: 40px; border-radius: 12px; border: 1px solid #e1e1e1; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">

        <div style="text-align: center; margin-bottom: 25px;">
            <h2 style="color: #1a1a1a; margin-bottom: 10px;">Password Reset Request</h2>
            <div style="height: 2px; width: 50px; background-color: #007bff; margin: auto;"></div>
        </div>

        <p style="color: #4a4a4a; line-height: 1.6; font-size: 15px;">
            Hello,
        </p>

        <p style="color: #4a4a4a; line-height: 1.6; font-size: 15px;">
            We received a request to reset the password for your election account. You can reset your password by
            clicking the button below:
        </p>

        <div style="text-align: center; margin: 35px 0;">
            <a href="{{ $url }}"
                style="background-color: #007bff; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;">
                Reset My Password
            </a>
        </div>

        <p style="color: #6c757d; font-size: 13px; line-height: 1.5;">
            <strong>Note:</strong> This password reset link is valid for <strong>60 minutes</strong> only.
            If you did not request a password reset, no further action is required and you can safely ignore this email.
        </p>

        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 30px 0;">

        <div style="text-align: center;">
            <p style="font-size: 11px; color: #adb5bd; margin-bottom: 4px;">
                &copy; {{ date('Y') }} School Automated Election System
            </p>
            <p style="font-size: 10px; color: #ced4da; margin: 0;">
                This is an automated message, please do not reply.
            </p>
        </div>
    </div>
</body>

</html>
