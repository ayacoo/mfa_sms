<?php
declare(strict_types=1);

namespace Ayacoo\MfaSms\Service\Sms;

use Aws\Sdk as AwsSdk;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

readonly class AwsSnsSmsSender implements SmsSenderInterface
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private ?AwsSdk                $awsSdk = null
    )
    {
    }

    public function send(string $phone, string $message, array $options = []): void
    {
        $extConfig = $this->extensionConfiguration->get('mfa_sms') ?? [];
        $region = (string)($extConfig['awsRegion'] ?? 'eu-central-1');
        $senderId = (string)($options['senderId'] ?? $extConfig['smsSenderId'] ?? 'TYPO3');

        $sdk = $this->awsSdk ?? new AwsSdk(['region' => $region, 'version' => 'latest']);
        $sns = $sdk->createSns();
        $sns->publish([
            'Message' => $message,
            'PhoneNumber' => $phone,
            'MessageAttributes' => [
                'AWS.SNS.SMS.SenderID' => [
                    'DataType' => 'String',
                    'StringValue' => $senderId,
                ],
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => 'Transactional',
                ],
            ],
        ]);
    }
}
