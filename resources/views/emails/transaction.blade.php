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

    {{-- Status bar --}}
    @php
      $confirmed = $transaction->status->value === 'confirmed';
      $barColor  = $confirmed ? '#34c759' : '#ff3b30';
      $statusLabel = $confirmed ? 'Confirmed' : 'Failed';
    @endphp
    <div style="height: 4px; background: {{ $barColor }};"></div>

    <div style="padding: 36px 40px 0;">

      {{-- Brand --}}
      <div style="margin-bottom: 28px;">
        <span style="font-size: 17px; font-weight: 700; color: #1d1d1f; letter-spacing: -0.4px;">CryptoVault</span>
      </div>

      {{-- Heading --}}
      <h1 style="font-size: 24px; font-weight: 700; color: #1d1d1f; margin: 0 0 6px; letter-spacing: -0.4px;">
        Transaction {{ $statusLabel }}
      </h1>
      <p style="font-size: 15px; color: #6e6e73; margin: 0 0 32px; line-height: 1.5;">
        @if ($confirmed)
          Your transaction has been confirmed on the blockchain.
        @else
          Your transaction could not be completed. No funds were moved.
        @endif
      </p>

      {{-- Status pill --}}
      <div style="display: inline-block; background: {{ $confirmed ? '#f0fff4' : '#fff0f0' }}; border: 1px solid {{ $confirmed ? '#b2f0c8' : '#fcc' }}; border-radius: 9999px; padding: 5px 14px; margin-bottom: 28px;">
        <span style="font-size: 13px; font-weight: 600; color: {{ $confirmed ? '#1a7f45' : '#d93025' }}; letter-spacing: 0.01em;">
          {{ strtoupper($statusLabel) }}
        </span>
      </div>

      {{-- Details table --}}
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 28px;">
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 12px 0; font-size: 13px; color: #6e6e73; width: 38%;">Chain</td>
          <td style="padding: 12px 0; font-size: 14px; color: #1d1d1f; font-weight: 500; text-align: right;">
            {{ strtoupper($transaction->chain_type->value) }}
          </td>
        </tr>
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 12px 0; font-size: 13px; color: #6e6e73;">Amount</td>
          <td style="padding: 12px 0; font-size: 14px; color: #1d1d1f; font-weight: 600; text-align: right;">
            {{ rtrim(rtrim(number_format((float) $transaction->amount, 8, '.', ''), '0'), '.') }}
            {{ $transaction->chain_type->nativeSymbol() }}
          </td>
        </tr>
        @if ($transaction->to_address)
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 12px 0; font-size: 13px; color: #6e6e73;">To</td>
          <td style="padding: 12px 0; font-size: 12px; color: #1d1d1f; font-family: 'Courier New', monospace; word-break: break-all; text-align: right;">
            {{ $transaction->to_address }}
          </td>
        </tr>
        @endif
        @if ($transaction->fee_usd)
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 12px 0; font-size: 13px; color: #6e6e73;">Network fee</td>
          <td style="padding: 12px 0; font-size: 14px; color: #1d1d1f; text-align: right;">
            ${{ number_format((float) $transaction->fee_usd, 4) }}
          </td>
        </tr>
        @endif
        @if ($transaction->confirmed_at)
        <tr style="border-bottom: 1px solid #f0f0f0;">
          <td style="padding: 12px 0; font-size: 13px; color: #6e6e73;">Confirmed at</td>
          <td style="padding: 12px 0; font-size: 14px; color: #1d1d1f; text-align: right;">
            {{ $transaction->confirmed_at->format('M j, Y · g:i A') }}
          </td>
        </tr>
        @endif
      </table>

      {{-- TX hash --}}
      @if ($transaction->tx_hash)
      <div style="background: #f5f5f7; border-radius: 10px; padding: 14px 16px; margin-bottom: 32px;">
        <p style="font-size: 11px; color: #6e6e73; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.06em;">Transaction hash</p>
        <p style="font-size: 12px; color: #1d1d1f; font-family: 'Courier New', monospace; margin: 0; word-break: break-all; line-height: 1.6;">{{ $transaction->tx_hash }}</p>
      </div>
      @endif

    </div>

    {{-- Footer --}}
    <div style="padding: 20px 40px 32px; border-top: 1px solid #f0f0f0;">
      <p style="font-size: 11px; color: #8e8e93; margin: 0; line-height: 1.7;">
        This notification was sent to <strong>{{ $user->email }}</strong> because you have transaction alerts enabled on your CryptoVault account.<br>
        If you did not initiate this transaction, contact support immediately.
      </p>
    </div>

  </div>

</div>

</body>
</html>
