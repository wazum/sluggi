<?php

$EM_CONF['sluggi'] = [
    'title' => 'sluggi - URL Slug Management',
    'description' => 'URL slug management with inline editing, auto-sync, locking, access control, and redirects',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'version' => '14.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'redirects' => '12.4.0-14.99.99',
        ],
    ],
];
