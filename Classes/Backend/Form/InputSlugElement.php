<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;
use function array_pop;
use function explode;
use function implode;
use function is_array;
use function preg_replace;
use function strlen;
use function strpos;
use function substr;

/**
 * Class InputSlugElement
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class InputSlugElement extends \TYPO3\CMS\Backend\Form\Element\InputSlugElement
{
    /**
     * Additional group/admin check for slug edit button
     */
    public function render(): array
    {
        $result = parent::render();

        if ('pages' !== $this->data['tableName']) {
            return $result;
        }

        if (!PermissionHelper::hasFullPermission()) {
            $result = $this->updateUiForRestrictedAccess($result);
        }

        return $this->overwriteCoreSlugElementLibrary($result);
    }

    protected function updateUiForRestrictedAccess(array $result): array
    {
        $page = $this->data['databaseRow'];
        $languageId = $this->getLanguageId($this->data['tableName'], $page);

        // Remove edit and recalculate buttons if slug segment is locked
        if (PermissionHelper::isLocked($page)) {
            $result['html'] .= '<script>var tx_sluggi_lock = true;</script>';
        } else {
            $synchronize = (bool) Configuration::get('synchronize');
            if (isset($page['tx_sluggi_sync']) && false === (bool) $page['tx_sluggi_sync']) {
                $synchronize = false;
            }
            if ($synchronize) {
                $result['html'] .= '<script>var tx_sluggi_sync = true;</script>';
            }
        }

        $mountRootPage = PermissionHelper::getTopmostAccessiblePage((int) $this->data['databaseRow']['uid']);
        $inaccessibleSlugSegments = SluggiSlugHelper::getSlug((int) $mountRootPage['pid'], $languageId);
        $prefix = ($this->data['customData'][$this->data['fieldName']]['slugPrefix'] ?? '') . $inaccessibleSlugSegments;
        $editableSlugSegments = $this->data['databaseRow']['slug'];
        $allowOnlyLastSegment = (bool) Configuration::get('last_segment_only');
        if (!empty($inaccessibleSlugSegments) && 0 === strpos($editableSlugSegments, $inaccessibleSlugSegments)) {
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

    protected function getLanguageId(string $table, array $row): int
    {
        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField']) &&
            !empty($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];

            return (int) ((is_array($row[$languageField]) ?
                    $row[$languageField][0] :
                    $row[$languageField]) ?? 0
            );
        }

        return 0;
    }

    protected function replaceValues(string $html, string $prefix, string $segments): string
    {
        return preg_replace(
            [
                '/(<input[\s]+class=".*?t3js-form-field-slug-input.*?".*?)placeholder="(.*?)"(.*?>)/ius',
                '/(<input[\s]+class=".*?t3js-form-field-slug-readonly".*?)title="(.*?)"(.*?)value="(.*?)"(.*?>)/ius',
                '/(<input[\s]+class=".*?t3js-form-field-slug-hidden.*?".*?)value="(.*?)"(.*?>)/ius',
                '/(<span class="input-group-addon">)(.*?)(<\/span>)/ius',
            ],
            [
                '$1' . 'placeholder="' . $segments . '"' . '$3',
                '$1' . 'title="' . $segments . '"' . '$3' . 'value="' . $segments . '"' . '$5',
                '$1' . 'value="' . $segments . '"' . '$3',
                '$1' . $prefix . '$3',
            ],
            $html
        );
    }

    protected function overwriteCoreSlugElementLibrary(array $result): array
    {
        $target = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Sluggi/SlugElement');
        foreach ($result['requireJsModules'][0]->getItems() as $item) {
            $target->instance(...$item['args']);
        }
        $result['requireJsModules'][0] = $target;

        return $result;
    }
}
