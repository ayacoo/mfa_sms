<?php
declare(strict_types=1);

namespace Ayacoo\MfaSms\Service\Sms;

use Aws\Sdk as AwsSdk;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class SmsSenderFactory
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private ?AwsSdk                $awsSdk = null,
        private ?AwsSnsSmsSender       $aws = null,
        private ?TwilioSmsSender       $twilio = null,
    )
    {
    }

    public function create(): SmsSenderInterface
    {
        $config = $this->extensionConfiguration->get('mfa_sms') ?? [];
        $provider = strtolower((string)($config['smsProvider'] ?? 'aws'));
        return match ($provider) {
            'twilio' => $this->twilio ?? new TwilioSmsSender($this->extensionConfiguration),
            default => $this->aws ?? new AwsSnsSmsSender($this->extensionConfiguration, $this->awsSdk),
        };
    }
}
