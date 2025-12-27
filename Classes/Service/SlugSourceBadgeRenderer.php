<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final readonly class SlugSourceBadgeRenderer
{
    /**
     * @param array{slot: int, role: string, chainSize: int} $metadata
     */
    public function renderBadgeWithMetadata(array $metadata, int $totalFields = 1, bool $hidden = false): string
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

        $style = $hidden ? ' style="display:none"' : '';

        return sprintf(
            '<span class="%s"%s title="This field influences the URL slug (priority %d%s)"><span class="sluggi-source-badge__icon">%s%s</span></span>',
            $cssClass,
            $style,
            $slot,
            $titleSuffix,
            $linkIcon,
            $numberBadge
        );
    }

    public function insertBadgeIntoHtml(string $html, string $badge, bool $hidden = false): string
    {
        $elementClass = Typo3Compatibility::getFormWizardsElementClass();
        $pattern = '/(<div class="[^"]*' . preg_quote($elementClass, '/') . '[^"]*">)\s*(<input)([^>]+type="text"[^>]*>)/s';
        $wrapperClass = $hidden ? '' : ' class="input-group"';
        $replacement = '$1<div' . $wrapperClass . '>' . $badge . '$2$3</div>';

        return preg_replace($pattern, $replacement, $html, 1) ?? $html;
    }

    public function markAsSourceField(string $html): string
    {
        $elementClass = Typo3Compatibility::getFormWizardsElementClass();
        $pattern = '/(<div class="[^"]*' . preg_quote($elementClass, '/') . '[^"]*">)\s*(<input)([^>]+type="text"[^>]*>)/s';
        $replacement = '$1$2 data-sluggi-source$3';

        return preg_replace($pattern, $replacement, $html, 1) ?? $html;
    }
}
