<?php

$EM_CONF['sluggi'] = [
    'title' => 'sluggi - URL Slug Management',
    'description' => 'Enhanced URL slug field with inline editing, automatic synchronization, locking, access control, and redirect handling. Replaces the core slug field with a modern interface.',
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
