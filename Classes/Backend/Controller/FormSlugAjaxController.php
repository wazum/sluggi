<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

final class FormSlugAjaxController extends \TYPO3\CMS\Backend\Controller\FormSlugAjaxController
{
    /**
     * @throws \RuntimeException
     * @throws SiteNotFoundException
     */
    public function suggestAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkRequest($request);
        $queryParameters = $request->getParsedBody() ?? [];
        $tableName = $queryParameters['tableName'];

        if ('pages' !== $tableName || PermissionHelper::hasFullPermission()) {
            return parent::suggestAction($request);
        }

        return $this->restrictedSuggestAction($request);
    }

    /**
     * @throws SiteNotFoundException
     */
    private function restrictedSuggestAction(ServerRequestInterface $request): ResponseInterface
    {
        $allowOnlyLastSegment = (bool) Configuration::get('last_segment_only');
        $queryParameters = $request->getParsedBody() ?? [];
        $values = $queryParameters['values'];
        $mode = $queryParameters['mode'];
        $tableName = $queryParameters['tableName'];
        $pid = (int) $queryParameters['pageId'];
        $parentPageId = (int) $queryParameters['parentPageId'];
        $recordId = (int) $queryParameters['recordId'];
        $languageId = (int) $queryParameters['language'];
        $fieldName = $queryParameters['fieldName'];

        $fieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] ?? [];
        if (empty($fieldConfig)) {
            throw new \RuntimeException('No valid field configuration for table ' . $tableName . ' field name ' . $fieldName . ' found.', 1535379534);
        }

        $evalInfo = !empty($fieldConfig['eval']) ? GeneralUtility::trimExplode(',', $fieldConfig['eval'], true) : [];
        $hasToBeUniqueInSite = \in_array('uniqueInSite', $evalInfo, true);
        $hasToBeUniqueInPid = \in_array('uniqueInPid', $evalInfo, true);

        $hasConflict = false;

        $recordData = $values;
        $recordData['pid'] = $pid;
        if (!empty($GLOBALS['TCA'][$tableName]['ctrl']['languageField'])) {
            $recordData[$GLOBALS['TCA'][$tableName]['ctrl']['languageField']] = $languageId;
        }

        $slug = GeneralUtility::makeInstance(SlugHelper::class, $tableName, $fieldName, $fieldConfig);
        if ('auto' === $mode) {
            // New page - Feed incoming values to generator
            $proposal = $slug->generate($recordData, $pid);
        } elseif ('recreate' === $mode) {
            $proposal = $slug->generate($recordData, $parentPageId);
            if ($allowOnlyLastSegment) {
                $parts = \explode('/', $proposal);
                $proposal = array_pop($parts);
                $pageRecord = BackendUtility::getRecordWSOL('pages', $recordId);
                $parts = \explode('/', $pageRecord['slug']);
                array_pop($parts);
                $proposal = rtrim(implode('/', $parts), '/') . '/' . ltrim($proposal, '/');
            }
        } elseif ('manual' === $mode) {
            // Existing record - Fetch full record and only validate against the new "slug" field.
            $proposal = $slug->sanitize($values['manual']);
            if ($allowOnlyLastSegment) {
                // Remove any slashes inside the slug proposal
                $proposal = preg_replace('#(?<!^)/(?!$)#', '-', $proposal);

                $pageRecord = BackendUtility::getRecordWSOL('pages', $recordId);
                $parts = \explode('/', $pageRecord['slug']);
                array_pop($parts);
                $proposal = rtrim(implode('/', $parts), '/') . '/' . ltrim($proposal, '/');
            }
        } else {
            throw new \RuntimeException('mode must be either "auto", "recreate" or "manual"', 1535835666);
        }

        $state = RecordStateFactory::forName($tableName)
            ->fromArray($recordData, $pid, $recordId);
        if ($hasToBeUniqueInSite && !$slug->isUniqueInSite($proposal, $state)) {
            $hasConflict = true;
            $proposal = $slug->buildSlugForUniqueInSite($proposal, $state);
        }
        if ($hasToBeUniqueInPid && !$slug->isUniqueInPid($proposal, $state)) {
            $hasConflict = true;
            $proposal = $slug->buildSlugForUniqueInPid($proposal, $state);
        }

        $mountRootPage = PermissionHelper::getTopmostAccessiblePage($pid);
        $inaccessibleSlugSegments = null;
        if (null !== $mountRootPage) {
            $inaccessibleSlugSegments = SluggiSlugHelper::getSlug($mountRootPage['pid'], $languageId);
        }
        if (!empty($inaccessibleSlugSegments)) {
            if (strpos($proposal, $inaccessibleSlugSegments) !== 0) {
                $proposal = $inaccessibleSlugSegments . $proposal;
            }
        }

        return new JsonResponse([
            'hasConflicts' => !$mode && $hasConflict,
            'manual' => $values['manual'] ?? '',
            'proposal' => $proposal,
            'inaccessibleSegments' => $inaccessibleSlugSegments,
            'lastSegmentOnly' => $allowOnlyLastSegment,
        ]);
    }
}
