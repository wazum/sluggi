<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final readonly class SlugSourceBadgeRenderer
{
    /**
     * @param array{slot: int, role: string, chainSize: int} $metadata
     */
    public function renderBadgeWithMetadata(array $metadata, int $totalFields = 1): string
    {
        $linkIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

        $slot = $metadata['slot'];
        $role = $metadata['role'];

        $titleSuffix = match ($role) {
            'preferred' => ', used if filled',
            'fallback' => ', fallback',
            default => '',
        };

        $cssClass = 'input-group-text sluggi-source-badge';
        if ($role === 'fallback') {
            $cssClass .= ' sluggi-source-badge--fallback';
        }

        $numberBadge = $totalFields > 1
            ? sprintf('<span class="sluggi-source-badge__number">%d</span>', $slot)
            : '';

        return sprintf(
            '<span class="%s" title="This field influences the URL slug (priority %d%s)"><span class="sluggi-source-badge__icon">%s%s</span></span>',
            $cssClass,
            $slot,
            $titleSuffix,
            $linkIcon,
            $numberBadge
        );
    }

    public function insertBadgeIntoHtml(string $html, string $badge, string $confirmButton = '', bool $hidden = false): string
    {
        $elementClass = Typo3Compatibility::getFormWizardsElementClass();
        $pattern = '/(<div class="[^"]*' . preg_quote($elementClass, '/') . '[^"]*">)\s*(<input)([^>]+type="text"[^>]*>)/s';
        $activeClass = $hidden ? '' : ' sluggi-source-group--active';
        $replacement = '$1<div class="sluggi-source-group' . $activeClass . '">' . $badge . '$2$3' . $confirmButton . '</div>';

        return preg_replace($pattern, $replacement, $html, 1) ?? $html;
    }

    public function markAsSourceField(string $html): string
    {
        $elementClass = Typo3Compatibility::getFormWizardsElementClass();
        $pattern = '/(<div class="[^"]*' . preg_quote($elementClass, '/') . '[^"]*">)\s*(<input)([^>]+type="text"[^>]*>)/s';
        $replacement = '$1$2 data-sluggi-source$3';

        return preg_replace($pattern, $replacement, $html, 1) ?? $html;
    }

    public function renderConfirmButton(): string
    {
        $checkIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';

        return sprintf(
            '<button type="button" class="input-group-text sluggi-source-confirm" title="Update URL path now">%s</button>',
            $checkIcon
        );
    }
}
