<?php
declare(strict_types=1);

namespace Ayacoo\MfaSms\Service\Sms;

interface SmsSenderInterface
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param string $phone E.164 formatted phone number (e.g. +491721234567)
     * @param string $message Message text to send
     * @param array $options Provider specific options, e.g. ['senderId' => 'TYPO3']
     */
    public function send(string $phone, string $message, array $options = []): void;
}
