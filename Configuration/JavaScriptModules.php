<?php

return [
    'dependencies' => ['backend'],
    'tags' => [
        'backend.form',
        'backend.module',
    ],
    'imports' => [
        '@wazum/sluggi/' => 'EXT:sluggi/Resources/Public/JavaScript/',
    ],
];
