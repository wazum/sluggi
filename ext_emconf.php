<?php

$EM_CONF['sluggi'] = [
    'title' => 'sluggi',
    'description' => 'The little TYPO3 9 slug helper',
    'category' => 'backend',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author_company' => 'wazum.com',
    'version' => '1.11.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.5-9.5.99',
        ],
        'suggests' => [
            'redirects' => ''
        ]
    ]
];
