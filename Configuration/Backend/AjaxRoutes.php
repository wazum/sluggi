<?php

declare(strict_types=1);

use Wazum\Sluggi\Controller\RecursiveSlugUpdateController;

return [
    'sluggi_recursive_slug_update' => [
        'path' => '/sluggi/recursive-slug-update',
        'target' => RecursiveSlugUpdateController::class . '::updateAction',
    ],
];
