<?php

declare(strict_types=1);

namespace Wazum\Sluggi\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;

final class SlugUpdateItemProvider extends AbstractProvider
{
    /**
     * @var array<string, array<string, string>>
     */
    protected $itemsConfiguration = [
        'recursiveSlugUpdate' => [
            'type' => 'item',
            'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:contextMenu.recursiveSlugUpdate',
            'iconIdentifier' => 'actions-refresh',
            'callbackAction' => 'recursiveSlugUpdate',
        ],
    ];

    public function canHandle(): bool
    {
        return $this->table === 'pages'
            && (int)$this->identifier > 0
            && $this->backendUser->isAdmin();
    }

    public function getPriority(): int
    {
        return 50;
    }

    /**
     * @param array<string, mixed> $items
     *
     * @return array<string, mixed>
     */
    public function addItems(array $items): array
    {
        $this->initDisabledItems();
        $localItems = $this->prepareItems($this->itemsConfiguration);

        if (isset($items['more']['childItems'])) {
            $items['more']['childItems'] = $items['more']['childItems'] + $localItems;
        } else {
            $items += $localItems;
        }

        return $items;
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if (in_array($itemName, $this->disabledItems, true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        return [
            'data-callback-module' => '@wazum/sluggi/context-menu-actions',
        ];
    }
}
