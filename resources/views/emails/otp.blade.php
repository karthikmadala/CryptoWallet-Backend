<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CryptoVault</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Helvetica Neue', sans-serif; background: #f5f5f7; margin: 0; padding: 40px 0;">

<div style="max-width: 520px; margin: 0 auto; padding: 0 16px;">

  {{-- Card --}}
  <div style="background: #ffffff; border-radius: 18px; border: 1px solid #e0e0e0; overflow: hidden;">

    {{-- Top accent bar --}}
    <div style="height: 4px; background: #0066cc;"></div>

    <div style="padding: 36px 40px 0;">

      {{-- Brand --}}
      <div style="margin-bottom: 32px;">
        <span style="font-size: 17px; font-weight: 700; color: #1d1d1f; letter-spacing: -0.4px;">CryptoVault</span>
      </div>

      {{-- Purpose-aware heading --}}
      @php
        $heading = match($purpose->value) {
          'registration'   => 'Verify your email',
          'login'          => 'Your sign-in code',
          'password_reset' => 'Reset your password',
          default          => 'Your verification code',
        };
        $subtext = match($purpose->value) {
          'registration'   => 'Enter this code to activate your CryptoVault account.',
          'login'          => 'Use this code to complete your sign-in. It expires in 5 minutes.',
          'password_reset' => 'Use this code to create a new password. It expires in 5 minutes.',
          default          => 'Use this code to complete your request. It expires in 5 minutes.',
        };
      @endphp

      <h1 style="font-size: 24px; font-weight: 700; color: #1d1d1f; margin: 0 0 8px; letter-spacing: -0.4px; line-height: 1.2;">
        {{ $heading }}
      </h1>
      <p style="font-size: 15px; color: #6e6e73; margin: 0 0 32px; line-height: 1.55;">
        {{ $subtext }}
      </p>

      {{-- OTP display --}}
      <div style="margin-bottom: 32px;">
        <p style="font-size: 11px; font-weight: 600; color: #6e6e73; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.08em;">Your code</p>
        <div style="background: #f5f5f7; border-radius: 12px; padding: 22px 24px; display: inline-block; min-width: 100%; box-sizing: border-box; text-align: center;">
          <span style="font-size: 44px; font-weight: 700; letter-spacing: 16px; color: #0066cc; font-variant-numeric: tabular-nums; font-family: 'Courier New', 'SF Mono', monospace;">{{ $otp }}</span>
        </div>
      </div>

      {{-- Expiry notice --}}
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 28px;">
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 11px 0; font-size: 13px; color: #6e6e73; width: 38%;">Expires in</td>
          <td style="padding: 11px 0; font-size: 14px; color: #1d1d1f; font-weight: 500; text-align: right;">5 minutes</td>
        </tr>
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 11px 0; font-size: 13px; color: #6e6e73;">Sent to</td>
          <td style="padding: 11px 0; font-size: 14px; color: #1d1d1f; font-weight: 500; text-align: right;">{{ $user->email }}</td>
        </tr>
        <tr>
          <td style="padding: 11px 0; font-size: 13px; color: #6e6e73;">Purpose</td>
          <td style="padding: 11px 0; font-size: 14px; color: #1d1d1f; font-weight: 500; text-align: right; text-transform: capitalize;">{{ str_replace('_', ' ', $purpose->value) }}</td>
        </tr>
      </table>

      {{-- Security notice --}}
      <div style="background: #fff8e6; border: 1px solid #ffe5a0; border-radius: 10px; padding: 14px 16px; margin-bottom: 32px;">
        <p style="font-size: 13px; color: #7a5800; margin: 0; line-height: 1.55;">
          <strong>Never share this code.</strong> CryptoVault will never ask for it via phone, chat, or email.
        </p>
      </div>

    </div>

    {{-- Footer --}}
    <div style="padding: 20px 40px 32px; border-top: 1px solid #f0f0f0;">
      <p style="font-size: 11px; color: #8e8e93; margin: 0; line-height: 1.7;">
        This email was sent to <strong>{{ $user->email }}</strong>.<br>
        If you did not request this code, you can safely ignore this email. No action is required.
      </p>
    </div>

  </div>

</div>

</body>
</html>
