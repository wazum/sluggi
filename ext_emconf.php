<?php

// phpcs:disable PSR12.Files.FileHeader.SpacingAfterBlock

/**
 * @noinspection PhpUndefinedVariableInspection
 *
 * @psalm-suppress PossiblyUndefinedGlobalVariable
 * @psalm-suppress UndefinedGlobalVariable
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'sluggi',
    'description' => 'The little TYPO3 slug helper',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'version' => '13.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
        ],
    ],
];
