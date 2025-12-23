<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/Classes')
    ->in(__DIR__ . '/Tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'yoda_style' => false,
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => false,
            'import_constants' => false,
        ],
        'phpdoc_line_span' => [
            'const' => 'multi',
            'method' => 'multi',
            'property' => 'multi',
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
