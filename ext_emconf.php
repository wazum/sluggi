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
    'version' => '12.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.99.99',
        ],
    ],
];
