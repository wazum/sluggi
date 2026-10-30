<?php

return [
    'ctrl' => [
        'title' => 'Test Translated Article',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'rootLevel' => -1,
        'security' => [
            'ignoreWebMountRestriction' => true,
            'ignoreRootLevelRestriction' => true,
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'Title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
        'slug' => [
            'label' => 'Slug',
            'config' => [
                'type' => 'slug',
                'generatorOptions' => [
                    'fields' => ['title'],
                ],
                'fallbackCharacter' => '-',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'title, slug',
        ],
    ],
];
