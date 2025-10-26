<?php
$EM_CONF['mfa_sms'] = [
    'title' => 'MFA SMS',
    'description' => 'MFA provider that sends 6-digit codes by SMS via Amazon SNS',
    'category' => 'plugin',
    'author' => 'Ayacoo',
    'author_email' => 'info@ayacoo.de',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
