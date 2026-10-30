<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm your registration · Cloud Computing 2025</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-outer { padding: 16px 8px !important; }
            .email-content { padding: 28px 24px !important; }
            .email-header { padding: 28px 24px !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f3f5f8;color:#253044;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Confirm your Cloud Computing 2025 registration. Your link is valid for 7 days.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f5f8;">
        <tr>
            <td class="email-outer" align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;border:1px solid #e0e5ed;border-radius:10px;background-color:#ffffff;overflow:hidden;">
                    <tr>
                        <td class="email-header" style="padding:32px 40px;background-color:#0b0f17;border-bottom:3px solid #6d91f5;">
                            <p style="margin:0;color:#f1f5f9;font-size:13px;font-weight:bold;letter-spacing:2px;">CLOUD COMPUTING</p>
                            <p style="margin:8px 0 0;color:#94a3b8;font-size:12px;letter-spacing:2px;">2025 / STUDENT REGISTRATION</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding:36px 40px;overflow-wrap:anywhere;word-break:break-word;">
                            <h1 style="margin:0 0 24px;color:#111827;font-size:26px;font-weight:600;line-height:1.3;letter-spacing:-0.5px;">One last step. Confirm your registration.</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Hello {{ $studentName }},</p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#526078;">Thank you for registering for Cloud Computing 2025. Confirm your email address to complete your registration.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 28px;border-top:1px solid #e5e9f0;border-bottom:1px solid #e5e9f0;">
                                <tr><td style="padding:20px 0 4px;font-size:12px;color:#64748b;">Course</td></tr>
                                <tr><td style="padding:0 0 16px;font-size:14px;color:#253044;">Cloud Computing 2025</td></tr>
                                <tr><td style="padding:0 0 4px;font-size:12px;color:#64748b;">Student name</td></tr>
                                <tr><td style="padding:0 0 16px;font-size:14px;color:#253044;">{{ $studentName }}</td></tr>
                                <tr><td style="padding:0 0 4px;font-size:12px;color:#64748b;">Student email</td></tr>
                                <tr><td style="padding:0 0 16px;font-size:14px;color:#253044;">{{ $studentEmail }}</td></tr>
                                <tr><td style="padding:0 0 4px;font-size:12px;color:#64748b;">Registered on</td></tr>
                                <tr><td style="padding:0 0 20px;font-size:14px;color:#253044;">{{ $registeredAt }}</td></tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;width:100%;">
                                <tr><td align="left"><a class="email-button" href="{{ $confirmationUrl }}" style="display:inline-block;padding:15px 24px;border-radius:6px;background-color:#6d91f5;color:#0b0f17;font-size:14px;font-weight:bold;text-decoration:none;line-height:20px;">Confirm Registration</a></td></tr>
                            </table>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.7;color:#526078;">This link is valid for 7 days and expires on {{ $expiresAt }}. Keep it private; it confirms your registration.</p>
                            <p style="margin:0 0 8px;font-size:12px;line-height:1.7;color:#64748b;">If the button doesn't work, copy and paste this link into your browser:</p>
                            <p style="margin:0 0 28px;word-break:break-all;overflow-wrap:anywhere;font-size:11px;line-height:1.7;"><a href="{{ $confirmationUrl }}" style="color:#4168c8;text-decoration:underline;word-break:break-all;">{{ $confirmationUrl }}</a></p>
                            <p style="margin:0;border-top:1px solid #e5e9f0;padding-top:20px;font-size:12px;line-height:1.7;color:#64748b;">If you didn't request this registration, you can ignore this email. No confirmation will take place unless this link is used.</p>
                        </td>
                    </tr>
                </table>
                <p style="margin:20px 0 0;font-size:11px;line-height:1.7;color:#64748b;">Cloud Computing 2025 · Learn with purpose. Build with confidence.</p>
            </td>
        </tr>
    </table>
</body>
</html>
