# TYPO3 Extension mfa_sms

## 1 Features

This extension adds an SMS-based Multi-Factor Authentication (MFA) provider to TYPO3.

- Sends one-time 6-digit authentication codes via SMS
- Supports multiple SMS backends: Amazon SNS (default) and Twilio
- Configurable sender, message text, region, and maximum verification attempts
- Integrates into TYPO3’s built-in MFA flow (setup, edit, authentication)

## 2 Hints

- Each authentication code is valid only once and expires after use.
- Phone numbers should be provided in international E.164 format (e.g. +491701234567).
- Keep your provider credentials secure and consider SMS costs and rate limits.
- The default provider is Amazon SNS; switch to Twilio via configuration if desired.

## 3 Usage

### 3.1 Prerequisites

To use this extension, you need the following requirements:

- PHP version 8.2 or higher
- TYPO3 version 13.4 or higher
- Composer-based TYPO3 installation
- Credentials for one supported SMS provider:
  - Amazon Web Services SNS (recommended default)
  - Twilio

### 3.2 Installation

#### Installation using Composer

The recommended way to install the extension is using Composer. Run the following command within your Composer-based TYPO3 project:

```
composer require ayacoo/mfa_sms
```

### 3.3 Configuration

Configure the extension via `config/system/settings.php` (EXTENSIONS section). Below are the available options with their defaults/example values:

```
'EXTENSIONS' => [
    'mfa_sms' => [
        // Select SMS provider: 'aws' (Amazon SNS, default) or 'twilio'
        'smsProvider' => 'aws',

        // Security & usability
        'maxAttempts' => '6',                      // Lock provider after N wrong attempts
        'smsMessage' => 'Ihr Sicherheitscode lautet: %s', // Use "%s" as placeholder for the code

        // Amazon SNS settings
        'awsRegion' => 'eu-central-1',            // e.g. eu-central-1
        'smsSenderId' => 'TYPO3',                 // Optional alphanumeric sender ID (country dependent)

        // Twilio settings (required if smsProvider = 'twilio')
        'twilioAccountSid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'twilioAuthToken' => 'your-twilio-auth-token',
        'twilioFrom' => '+1234567890',            // Your Twilio phone number
        'twilioMessagingServiceSid' => '',        // Optional: Messaging Service SID
    ],
],
```

Notes:
- The `smsMessage` must contain a `%s` placeholder which is replaced by the generated 6‑digit code.
- For Amazon SNS, `smsSenderId` support depends on destination country rules.
- For Twilio, either `twilioFrom` (phone number) or a `twilioMessagingServiceSid` must be configured per your setup.

### 3.4 Activation and use in TYPO3

1. Log in to the TYPO3 Backend.
2. Open "User settings" → "Multi-factor Authentication".
3. Add the provider "SMS authentication code".
4. Enter your mobile number and save to activate the provider.
5. On next login, you will receive a 6‑digit code via SMS; enter it to complete authentication.

## 4 Available languages

- German
- English

## 5 Support

If you are happy with the extension and would like to support it in any way, I would appreciate the support of social institutions.

[1]: https://getcomposer.org/
[2]: https://aws.amazon.com/sns/
[3]: https://www.twilio.com/sms
