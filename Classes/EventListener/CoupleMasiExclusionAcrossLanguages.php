<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use Wazum\Sluggi\Service\MasiCompatibilityService;

/**
 * masi's exclusion flag ships with allowLanguageSynchronization, so an editor can
 * unhook it and let it differ per language. Nothing in masi is built for that —
 * its own migration sets the flag on every translation, and its overlay is not
 * fallback aware — while sluggi resolves paths from the language agnostic
 * rootline. Coupling the field removes the divergence instead of reproducing it.
 */
final readonly class CoupleMasiExclusionAcrossLanguages
{
    public function __construct(
        private MasiCompatibilityService $masiCompatibilityService,
    ) {
    }

    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        if (!$this->masiCompatibilityService->isActive()) {
            return;
        }

        $tca = $event->getTca();
        $fieldName = $this->masiCompatibilityService->getExclusionFieldName();
        if (!isset($tca['pages']['columns'][$fieldName])) {
            return;
        }

        $tca['pages']['columns'][$fieldName]['l10n_mode'] = 'exclude';
        unset($tca['pages']['columns'][$fieldName]['config']['behaviour']['allowLanguageSynchronization']);

        $event->setTca($tca);
    }
}
