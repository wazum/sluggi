<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sluggi ext_tables Test Fixture',
    'description' => 'Loads its ext_tables.php after sluggi. Not intended for production use.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'sluggi' => '',
        ],
    ],
];
