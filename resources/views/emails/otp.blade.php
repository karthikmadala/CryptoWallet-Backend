<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CryptoVault</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Helvetica Neue', sans-serif; background: #f5f5f7; margin: 0; padding: 40px 0;">

<div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 40px; border: 1px solid #e0e0e0;">

  {{-- Logo / Brand --}}
  <div style="margin-bottom: 32px;">
    <span style="font-size: 20px; font-weight: 700; color: #272729; letter-spacing: -0.5px;">CryptoVault</span>
  </div>

  {{-- Heading --}}
  <h1 style="font-size: 22px; font-weight: 700; color: #272729; margin: 0 0 8px;">
    @switch($purpose->value)
      @case('registration') Verify your email @break
      @case('login') Sign-in code @break
      @case('password_reset') Reset your password @break
      @default Your verification code @break
    @endswitch
  </h1>

  <p style="font-size: 14px; color: #6e6e73; margin: 0 0 28px; line-height: 1.5;">
    @switch($purpose->value)
      @case('registration') Enter this code to verify your CryptoVault account. @break
      @case('login') Use this code to complete your sign-in. @break
      @case('password_reset') Use this code to reset your password. @break
      @default Use this code to complete your request. @break
    @endswitch
  </p>

  {{-- OTP Box --}}
  <div style="background: #f5f5f7; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 28px;">
    <span style="font-size: 40px; font-weight: 700; letter-spacing: 14px; color: #0066cc; font-variant-numeric: tabular-nums;">{{ $otp }}</span>
  </div>

  {{-- Expiry Notice --}}
  <p style="font-size: 13px; color: #444; margin: 0 0 20px; line-height: 1.6;">
    This code expires in <strong>5 minutes</strong>. Do not share it with anyone — CryptoVault will never ask for this code via phone or chat.
  </p>

  {{-- Security Footer --}}
  <div style="border-top: 1px solid #e0e0e0; padding-top: 20px; margin-top: 20px;">
    <p style="font-size: 11px; color: #8e8e93; margin: 0; line-height: 1.6;">
      This request was initiated for <strong>{{ $user->email }}</strong>.<br>
      If you did not request this, you can safely ignore this email.<br>
      No action is required from your account.
    </p>
  </div>

</div>

</body>
</html>
