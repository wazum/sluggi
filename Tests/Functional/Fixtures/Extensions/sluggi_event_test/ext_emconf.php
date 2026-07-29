<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sluggi Event Test Fixture',
    'description' => 'Listens to the slug source field event and replaces the title. Not intended for production use.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
        ],
    ],
];
