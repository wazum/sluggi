<?php

$EM_CONF['sluggi'] = [
    'title' => 'sluggi',
    'description' => 'The little TYPO3 slug helper',
    'category' => 'backend',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author_company' => 'wazum.com',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
            'redirects' => '*'
        ]
    ]
];
