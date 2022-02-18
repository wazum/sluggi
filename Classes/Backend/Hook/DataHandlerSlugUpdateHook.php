<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;
use function array_merge;
use function in_array;
use function str_replace;
use function strpos;
use function substr;

/**
 * Class DataHandlerSlugUpdateHook
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class DataHandlerSlugUpdateHook
{
    /**
     * @var SlugService
     */
    protected $slugService;

    /**
     * @var string[]
     */
    protected $slugSetIncoming;

    public function __construct(SlugService $slugService)
    {
        $this->slugService = $slugService;
    }

    /**
     * @param string|int $id (id could be string, for this reason no type hint)
     */
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        $id,
        DataHandler $dataHandler
    ): void {
        if (
            'pages' !== $table
            // This is set in \TYPO3\CMS\Backend\History\RecordHistoryRollback::performRollback,
            // so we use it as a flag to ignore the update
            || $dataHandler->dontProcessTransformations
            || !MathUtility::canBeInterpretedAsInteger($id)
            || !$dataHandler->checkRecordUpdateAccess($table, $id, $incomingFieldArray)
            || $this->isNestedHookInvocation($dataHandler)
        ) {
            return;
        }

        if (!empty($incomingFieldArray['slug'])) {
            $this->slugSetIncoming[(int) $id] = true;
        }

        $synchronize = (bool) Configuration::get('synchronize');
        $allowOnlyLastSegment = (bool) Configuration::get('last_segment_only');

        if (isset($incomingFieldArray['tx_sluggi_sync']) && false === (bool) $incomingFieldArray['tx_sluggi_sync']) {
            $synchronize = false;
        }
        if ($synchronize) {
            $record = BackendUtility::getRecordWSOL($table, (int) $id);
            $data = array_merge($record, $incomingFieldArray);
            if ((bool) $data['tx_sluggi_sync']) {
                $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];
                /** @var SlugHelper $helper */
                $helper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
                $incomingFieldArray['slug'] = $helper->generate($data, (int) $data['pid']);
            }
        } elseif (isset($incomingFieldArray['slug']) && $allowOnlyLastSegment && !PermissionHelper::hasFullPermission()) {
            $record = BackendUtility::getRecordWSOL($table, (int) $id);
            $languageId = $record['sys_language_uid'];
            $inaccessibleSlugSegments = $this->getInaccessibleSlugSegments($id, $languageId);
            // Prepend the parent page slug
            $parentSlug = SluggiSlugHelper::getSlug($record['pid'], $languageId);
            if (false !== strpos(substr($incomingFieldArray['slug'], 1), '/')) {
                $this->setFlashMessage(
                    LocalizationUtility::translate('message.slashesNotAllowed', 'sluggi'),
                    FlashMessage::WARNING
                );
            }
            $incomingFieldArray['slug'] = $inaccessibleSlugSegments .
                str_replace($inaccessibleSlugSegments, '', $parentSlug) .
                '/' . str_replace('/', '-', substr($incomingFieldArray['slug'], 1));
        }
    }

    /**
     * Determines whether our identifier is part of correlation id aspects.
     * In that case it would be a nested call which has to be ignored.
     */
    protected function isNestedHookInvocation(DataHandler $dataHandler): bool
    {
        $correlationId = $dataHandler->getCorrelationId();
        $correlationIdAspects = $correlationId ? $correlationId->getAspects() ?? [] : [];

        return in_array(SlugService::CORRELATION_ID_IDENTIFIER, $correlationIdAspects, true);
    }

    protected function getInaccessibleSlugSegments(int $pageId, int $languageId): string
    {
        $mountRootPage = PermissionHelper::getTopmostAccessiblePage($pageId);

        return SluggiSlugHelper::getSlug($mountRootPage['pid'], $languageId);
    }

    protected function setFlashMessage(string $text, int $severity): void
    {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(FlashMessage::class, $text, '', $severity);
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }

    /**
     * @param int|string $id
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        $id,
        array $incomingFieldArray,
        DataHandler $dataHandler
    ): void {
        if (
            'pages' !== $table
            || 'update' !== $status
            || $this->isNestedHookInvocation($dataHandler)
        ) {
            return;
        }

        // We have to double-check again here,
        // as the slug is empty e.g. if the title is changed via inline editing in page tree
        if (!isset($this->slugSetIncoming[(int) $id])) {
            $synchronize = (bool) Configuration::get('synchronize');

            if (isset($incomingFieldArray['tx_sluggi_sync']) && false === (bool) $incomingFieldArray['tx_sluggi_sync']) {
                $synchronize = false;
            }
            if ($synchronize) {
                $record = BackendUtility::getRecordWSOL($table, (int) $id);
                $data = array_merge($record, $incomingFieldArray);
                if ($data['tx_sluggi_sync']) {
                    $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];
                    /** @var SlugHelper $helper */
                    $helper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
                    $incomingFieldArray['slug'] = $helper->generate($data, (int) $data['pid']);
                }
                if (!empty($incomingFieldArray['slug']) && $incomingFieldArray['slug'] !== $record['slug']) {
                    $this->slugService->rebuildSlugsForSlugChange(
                        $id,
                        (string) $record['slug'],
                        $incomingFieldArray['slug'],
                        $dataHandler->getCorrelationId()
                    );
                }
            }
        }
    }
}
