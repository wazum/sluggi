<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use Wazum\Sluggi\Helper\Configuration;

class InputTextWithSlugImpactElement extends InputTextElement
{
    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        if ($this->isForPage() && $this->isSynchronizeActive()) {
            return $this->renderWithImpactMark();
        }

        return parent::render();
    }

    /**
     * @return array<string, mixed>
     */
    protected function renderWithImpactMark(): array
    {
        $result = parent::render();
        $result['html'] = \preg_replace(
            '/(<input.*?class=")(.*?)"/im',
            '$1$2 slug-impact"',
            $result['html']
        );

        return $result;
    }

    protected function isForPage(): bool
    {
        return 'pages' === $this->data['tableName'];
    }

    protected function isSynchronizeActive(): bool
    {
        return (bool) Configuration::get('synchronize');
    }
}
