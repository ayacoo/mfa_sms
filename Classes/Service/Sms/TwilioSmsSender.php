<?php
declare(strict_types=1);

namespace Ayacoo\MfaSms\Service\Sms;

use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Twilio\Rest\Client;

readonly class TwilioSmsSender implements SmsSenderInterface
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ConfigurationException
     */
    public function send(string $phone, string $message, array $options = []): void
    {
        $extConfig = $this->extensionConfiguration->get('mfa_sms') ?? [];
        $accountSid = (string)($extConfig['twilioAccountSid'] ?? '');
        $authToken = (string)($extConfig['twilioAuthToken'] ?? '');
        $from = (string)($extConfig['twilioFrom'] ?? ($options['from'] ?? ''));
        $messagingServiceSid = (string)($extConfig['twilioMessagingServiceSid'] ?? '');

        if ($accountSid === '' || $authToken === '') {
            throw new \RuntimeException('Twilio credentials are not configured.');
        }
        if ($from === '' && $messagingServiceSid === '') {
            throw new \RuntimeException('Twilio from number or Messaging Service SID must be configured.');
        }

        $client = new Client($accountSid, $authToken);
        $params = [
            'body' => $message,
        ];
        if ($messagingServiceSid !== '') {
            $params['messagingServiceSid'] = $messagingServiceSid;
        } else {
            $params['from'] = $from;
        }

        try {
            // The Messages->create method throws exceptions for non-2xx responses
            $client->messages->create($phone, $params);
        } catch (TwilioException $e) {
            throw new \RuntimeException('Twilio API error: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
