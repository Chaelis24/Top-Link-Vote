<!DOCTYPE html>
<html>

<body
    style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; padding: 20px; margin: 0;">
    <div
        style="max-width: 500px; margin: 20px auto; background: #ffffff; padding: 40px; border-radius: 12px; border: 1px solid #e1e1e1; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">

        <div style="text-align: center; margin-bottom: 25px;">
            <h2 style="color: #1a1a1a; margin-bottom: 10px;">Verify Your Account</h2>
            <div style="height: 2px; width: 50px; background-color: #007bff; margin: auto;"></div>
        </div>

        <p style="color: #4a4a4a; line-height: 1.6; font-size: 15px;">
            Hello,
        </p>

        <p style="color: #4a4a4a; line-height: 1.6; font-size: 15px;">
            We received a request to verify your account for the election system. Please use the verification code below
            to complete your setup:
        </p>

        <div style="text-align: center; margin: 35px 0;">
            <span
                style="background-color: #f8f9fa; color: #007bff; padding: 15px 35px; border-radius: 8px; font-weight: bold; font-size: 28px; display: inline-block; letter-spacing: 5px; border: 2px dashed #007bff;">
                {{ $code }}
            </span>
        </div>

        <p style="color: #6c757d; font-size: 13px; line-height: 1.5;">
            <strong>Note:</strong> This verification code is valid for <strong>10 minutes</strong> only.
            If you did not request this code, no further action is required and you can safely ignore this email.
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
