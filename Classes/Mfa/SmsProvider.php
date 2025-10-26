<?php

declare(strict_types=1);

namespace Ayacoo\MfaSms\Mfa;

use Aws\Sdk as AwsSdk;
use Ayacoo\MfaSms\Service\Sms\SmsSenderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaViewType;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class SmsProvider implements MfaProviderInterface
{
    protected array $extensionConfiguration;

    protected ServerRequestInterface $request;

    public function __construct(
        protected Context              $context,
        protected ResponseFactory      $responseFactory,
        protected ViewFactoryInterface $viewFactory,
        ExtensionConfiguration         $extensionConfiguration,
        protected ?AwsSdk              $awsSdk = null,
        protected ?SmsSenderInterface  $smsSender = null,
    ) {
        $this->extensionConfiguration = $extensionConfiguration->get('mfa_sms') ?? [];
    }

    public function canProcess(ServerRequestInterface $request): bool
    {
        return true;
    }

    public function isActive(MfaProviderPropertyManager $propertyManager): bool
    {
        return (bool)$propertyManager->getProperty('active');
    }

    public function isLocked(MfaProviderPropertyManager $propertyManager): bool
    {
        $attempts = (int)$propertyManager->getProperty('attempts', 0);
        return $propertyManager->hasProviderEntry() && $attempts >= $this->getMaxAttempts();
    }

    public function handleRequest(
        ServerRequestInterface     $request,
        MfaProviderPropertyManager $propertyManager,
        MfaViewType                $type
    ): ResponseInterface {
        $this->request = $request;
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:mfa_sms/Resources/Private/Templates/Mfa'],
            request: $request,
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $view->assign('providerIdentifier', $propertyManager->getIdentifier());

        switch ($type) {
            case MfaViewType::SETUP:
            case MfaViewType::EDIT:
                $output = $this->prepareEditView($view, $propertyManager);
                break;
            case MfaViewType::AUTH:
                $output = $this->prepareAuthView($request, $view, $propertyManager);
                break;
        }
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($output ?? '');

        return $response;
    }

    public function verify(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->isActive($propertyManager) || $this->isLocked($propertyManager)) {
            return false;
        }

        $authCodeInput = trim((string)($request->getQueryParams()['authCode'] ?? $request->getParsedBody()['authCode'] ?? ''));
        $properties = $propertyManager->getProperties();

        if ($authCodeInput !== ($properties['authCode'] ?? '')) {
            $properties['attempts'] = (isset($properties['attempts']) && (int)$properties['attempts'] ? (int)$properties['attempts'] : 0);
            $properties['attempts']++;
            $propertyManager->updateProperties($properties);
            return false;
        }

        $properties['authCode'] = '';
        $properties['attempts'] = 0;
        $properties['lastUsed'] = $this->context->getPropertyFromAspect('date', 'timestamp');

        return $propertyManager->updateProperties($properties);
    }

    public function activate(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        return $this->update($request, $propertyManager);
    }

    public function unlock(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->isActive($propertyManager) || !$this->isLocked($propertyManager)) {
            return false;
        }
        return $propertyManager->updateProperties(['attempts' => 0]);
    }

    public function deactivate(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->isActive($propertyManager)) {
            return false;
        }
        return $propertyManager->updateProperties(['active' => false]);
    }

    public function update(ServerRequestInterface $request, MfaProviderPropertyManager $propertyManager): bool
    {
        if (!$this->canProcess($request)) {
            return false;
        }

        $phone = trim((string)($request->getParsedBody()['phone'] ?? ''));
        if (!$this->checkValidPhone($phone)) {
            return false;
        }

        $properties = [
            'phone' => $phone,
            'active' => true
        ];
        return $propertyManager->hasProviderEntry()
            ? $propertyManager->updateProperties($properties)
            : $propertyManager->createProviderEntry($properties);
    }

    protected function sendAuthCodeSms(MfaProviderPropertyManager $propertyManager, bool $resend = false): void
    {
        $newAuthCode = false;
        $authCode = $propertyManager->getProperty('authCode');
        if (empty($authCode)) {
            $authCode = $this->generateAuthCode();
            $propertyManager->updateProperties(['authCode' => $authCode]);
            $newAuthCode = true;
        }

        if ($newAuthCode || $resend) {
            $messageTemplate = (string)($this->extensionConfiguration['smsMessage'] ?? 'Ihr Sicherheitscode lautet: %s');
            $message = sprintf($messageTemplate, $authCode);
            try {
                $this->smsSender->send(
                    (string)$propertyManager->getProperty('phone'),
                    $message,
                    ['senderId' => (string)($this->extensionConfiguration['smsSenderId'] ?? 'TYPO3')]
                );
            } catch (\Throwable $e) {
                $this->showLocalizedFlashMessage('error.sms.send');
            }
        }
    }

    protected function prepareEditView(ViewInterface $view, MfaProviderPropertyManager $propertyManager): string
    {
        $view->assignMultiple([
            'phone' => (empty($propertyManager->getProperty('phone')) ? ($GLOBALS['BE_USER']->user['telephone'] ?? '') : $propertyManager->getProperty('phone')),
            'lastUsed' => $this->getDateTime($propertyManager->getProperty('lastUsed', 0)),
            'updated' => $this->getDateTime($propertyManager->getProperty('updated', 0)),
        ]);

        return $view->render('Edit');
    }

    protected function prepareAuthView(ServerRequestInterface $request, ViewInterface $view, MfaProviderPropertyManager $propertyManager): string
    {
        $queryParams = $request->getQueryParams();
        $resend = !empty($queryParams['resend']) && $queryParams['resend'] === '1';

        $this->sendAuthCodeSms($propertyManager, $resend);
        $view->assignMultiple([
            'isLocked' => $this->isLocked($propertyManager),
            'resendLink' => '?' . http_build_query(array_merge($queryParams, ['resend' => '1'])),
        ]);

        return $view->render('Auth');
    }

    protected function generateAuthCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function getDateTime(int $timestamp): string
    {
        if ($timestamp === 0) {
            return '';
        }
        return date(
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'],
            $timestamp
        ) ?: '';
    }

    protected function checkValidPhone(string $phone): bool
    {
        $messageKey = null;
        if (empty($phone)) {
            $messageKey = 'error.phone.empty';
        } elseif (!$this->isPhoneValid($phone)) {
            $messageKey = 'error.phone.notvalid';
        }

        if ($messageKey !== null) {
            $this->showLocalizedFlashMessage($messageKey);
            return false;
        }
        return true;
    }

    public function isPhoneValid(string $phone): bool
    {
        // Very basic E.164 validation: starts with + and 8-15 digits
        return (bool)preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }

    protected function showLocalizedFlashMessage(string $messageKey): void
    {
        $errorMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->showLocalizedMessage($messageKey . '.message'),
            $this->showLocalizedMessage($messageKey . '.title'),
            ContextualFeedbackSeverity::ERROR,
            true
        );
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($errorMessage);
    }

    protected function showLocalizedMessage(string $messageKey, array $params = []): string
    {
        return LocalizationUtility::translate($messageKey, 'mfa_sms', $params) ?? $messageKey;
    }

    protected function getMaxAttempts(): int
    {
        $maxAttempts = (isset($this->extensionConfiguration['maxAttempts']) ? (int)$this->extensionConfiguration['maxAttempts'] : 9999999);
        return ($maxAttempts !== -1 ? $maxAttempts : 9999999);
    }
}
