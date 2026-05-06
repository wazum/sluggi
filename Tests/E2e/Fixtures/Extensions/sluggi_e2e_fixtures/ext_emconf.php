<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sluggi E2E Test Fixtures',
    'description' => 'Configures the pages slug generator with a nav_title/title fallback chain. Not intended for production use.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
        ],
    ],
];
