<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

/**
 * Class InputSlugElement
 * @package Wazum\Sluggi\Backend\Form
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class InputSlugElement extends \TYPO3\CMS\Backend\Form\Element\InputSlugElement
{

    /**
     * Additional group/admin check for slug edit button
     *
     * @return array
     */
    public function render(): array
    {
        $result = parent::render();

        if ($this->data['tableName'] !== 'pages') {
            return $result;
        }

        $languageId = $this->getLanguageId($this->data['tableName'], $this->data['databaseRow']);
        $page = $this->data['databaseRow'];

        if (!PermissionHelper::hasFullPermission()) {
            // Remove edit and recalculate buttons if slug segment is locked
            if (PermissionHelper::isLocked($page)) {
                $result['html'] = $this->removeButtonsFields($result['html']);
            }

            $mountRootPage = PermissionHelper::getTopmostAccessiblePage((int)$this->data['databaseRow']['uid']);
            $inaccessibleSlugSegments = SluggiSlugHelper::getSlug((int)$mountRootPage['pid'], $languageId);
            $prefix = $this->getPrefix($this->data['site'], $languageId) . $inaccessibleSlugSegments;
            $editableSlugSegments = $this->data['databaseRow']['slug'];
            if (!empty($inaccessibleSlugSegments) && strpos($this->data['databaseRow']['slug'], $inaccessibleSlugSegments) === 0) {
                $editableSlugSegments = substr($this->data['databaseRow']['slug'], strlen($inaccessibleSlugSegments));
            }

            $result['html'] = $this->replaceValues($result['html'], $prefix, $editableSlugSegments);
        }

        return $result;
    }

    /**
     * @param string $table
     * @param array $row
     * @return int
     */
    protected function getLanguageId(string $table, array $row): int
    {
        $languageId = 0;

        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField']) &&
            !empty($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
            $languageId = (int)(
                (is_array($row[$languageField]) ?
                    $row[$languageField][0] :
                    $row[$languageField]) ?? 0
            );
        }

        return $languageId;
    }

    /**
     * @param string $html
     * @return string
     */
    protected function removeButtonsFields(string $html): string
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query("//*[contains(@class, 'input-group-btn') or contains(@class, 't3js-form-field-slug-input') or contains(@class, 't3js-form-field-slug-hidden')]");
        /** @var DOMNode $node */
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }

        return $document->saveHTML();
    }

    /**
     * @param string $html
     * @param string $prefix
     * @param string $segments
     * @return string
     */
    protected function replaceValues(string $html, string $prefix, string $segments): string
    {
        $result = preg_replace(
            [
                '/(<input.*?class=".*?t3js-form-field-slug-input.*?".*?)placeholder="(.*?)"(.*?>)/ius',
                '/(<input.*?class=".*?t3js-form-field-slug-readonly.*?".*?)data-title="(.*?)"(.*?)value="(.*?)"(.*?>)/ius',
                '/(<input.*?class=".*?t3js-form-field-slug-hidden.*?".*?)value="(.*?)"(.*?>)/ius',
                '/(<span class="input-group-addon">)(.*?)(<\/span>)/ius',
                '/(<span class="t3js-form-proposal-accepted hidden label label-success">Congrats, this page will look like )(.*?)(<span>)/ius',
                '/(<span class="t3js-form-proposal-different hidden label label-warning">Hmm, that is taken, how about )(.*?)(<span>)/ius',
            ],
            [
                '$1' . 'placeholder="' . $segments . '"' . '$3',
                '$1' . 'data-title="' . $segments . '"' . '$3' . 'value="' . $segments . '"' . '$5',
                '$1' . 'value="' . $segments . '"' . '$3',
                '$1' . $prefix . '$3',
                '$1' . $prefix . '$3',
                '$1' . $prefix . '$3'
            ],
            $html
        );

        return $result;
    }

}
