<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

final class InputSlugElement extends \TYPO3\CMS\Backend\Form\Element\InputSlugElement
{
    /**
     * @return array<array-key, mixed>
     */
    public function render(): array
    {
        $result = parent::render();
        if (!$this->isForPage()) {
            return $result;
        }

        $result = $this->setStatusDataAttributes($result);

        if (!PermissionHelper::hasFullPermission()) {
            $result = $this->updateUiForRestrictedAccess($result);
        }

        // Replace the core slug element JavaScript module
        $target = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Sluggi/slug-element');
        foreach ($result['requireJsModules'][0]->getItems() as $item) {
            $target->instance(...$item['args']);
        }
        $result['requireJsModules'][0] = $target;

        return $result;
    }

    private function setStatusDataAttributes(array $result): array
    {
        $attributes = [
            'sync' => 'data-tx-sluggi-sync="0"',
            'lock' => 'data-tx-sluggi-lock="0"',
        ];
        if ($this->isSynchronizationActive()) {
            $attributes['sync'] = 'data-tx-sluggi-sync="1"';
        }
        if ($this->isPageSlugLocked()) {
            $attributes['lock'] = 'data-tx-sluggi-lock="1"';
        }
        $pattern = '/(<input[^>]*class="[^"]*?t3js-form-field-slug-input[^"]*?")([^>]*>)/i';
        $result['html'] = preg_replace_callback($pattern, static function ($matches) use ($attributes) {
            return $matches[1] . ' ' . implode(' ', $attributes) . $matches[2];
        }, $result['html']);

        return $result;
    }

    private function updateUiForRestrictedAccess(array $result): array
    {
        $languageId = $this->getLanguageId($this->data['tableName'], $this->data['databaseRow']);
        $mountRootPage = PermissionHelper::getTopmostAccessiblePage((int) $this->data['databaseRow']['uid']);
        // This is the case when a new page is generated through the context menu
        if (null === $mountRootPage) {
            return $result;
        }

        $inaccessibleSlugSegments = SluggiSlugHelper::getSlug((int) $mountRootPage['pid'], $languageId);
        $prefix = ($this->data['customData'][$this->data['fieldName']]['slugPrefix'] ?? '') . $inaccessibleSlugSegments;
        $editableSlugSegments = $this->data['databaseRow']['slug'];
        $allowOnlyLastSegment = (bool) Configuration::get('last_segment_only');
        if (!empty($inaccessibleSlugSegments) && 0 === strncmp($editableSlugSegments, $inaccessibleSlugSegments, strlen($inaccessibleSlugSegments))) {
            $editableSlugSegments = substr($editableSlugSegments, strlen($inaccessibleSlugSegments));
        }
        if ($allowOnlyLastSegment && !empty($editableSlugSegments)) {
            $segments = explode('/', $editableSlugSegments);
            $editableSlugSegments = '/' . array_pop($segments);
            $prefix .= implode('/', $segments);
        }

        $result['html'] = $this->replaceValues($result['html'], $prefix, $editableSlugSegments);

        return $result;
    }

    private function getLanguageId(string $table, array $row): int
    {
        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
            && !empty($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];

            return (int) ((is_array($row[$languageField])
                ? $row[$languageField][0]
                : $row[$languageField]) ?? 0
            );
        }

        return 0;
    }

    private function replaceValues(string $html, string $prefix, string $segments): string
    {
        return preg_replace(
            [
                '/(<input[\s]+class=".*?t3js-form-field-slug-input.*?".*?)placeholder="(.*?)"(.*?>)/ius',
                '/(<input[\s]+class=".*?t3js-form-field-slug-readonly".*?)title="(.*?)"(.*?)value="(.*?)"(.*?>)/ius',
                '/(<input[\s]+class=".*?t3js-form-field-slug-hidden.*?".*?)value="(.*?)"(.*?>)/ius',
                '/(<span class="input-group-addon">)(.*?)(<\/span>)/ius',
            ],
            [
                '$1placeholder="' . $segments . '"$3',
                '$1title="' . $segments . '"$3value="' . $segments . '"$5',
                '$1value="' . $segments . '"$3',
                '$1' . $prefix . '$3',
            ],
            $html
        );
    }

    private function isForPage(): bool
    {
        return 'pages' === $this->data['tableName'];
    }

    private function isSynchronizationActive(): bool
    {
        return Configuration::get('synchronize') && $this->data['databaseRow']['tx_sluggi_sync'];
    }

    private function isPageSlugLocked(): bool
    {
        return (bool) ($this->data['databaseRow']['slug_locked'] ?? false);
    }
}
