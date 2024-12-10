<?php

declare(strict_types=1);

return [
    'dependencies' => ['core', 'backend', 'redirects'],
    'imports' => [
        '@wazum/sluggi/' => 'EXT:sluggi/Resources/Public/JavaScript/',
        // Overwrite the core JavaScript module
        '@typo3/redirects/event-handler.js' => 'EXT:sluggi/Resources/Public/JavaScript/event-handler.js',
    ],
];
