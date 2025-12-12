<?php

$EM_CONF['sluggi'] = [
    'title' => 'sluggi',
    'description' => 'Enhanced TYPO3 URL slug management (The little TYPO3 Slug Helper)',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'version' => '14.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'redirects' => '12.4.0-14.99.99',
        ],
    ],
];
