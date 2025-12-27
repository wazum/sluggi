<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait FormSlugAjaxControllerTrait
{
    public function suggestAction(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->sanitizeSlashesInSourceFields($request);

        $response = parent::suggestAction($request);
        $data = json_decode((string)$response->getBody(), true);

        if ($data['hasConflicts'] ?? false) {
            $data['slug'] = $this->getOriginalSlug($request);
        }

        return new JsonResponse($data);
    }

    /**
     * Replace slashes in source field values with the fallback character.
     * TYPO3 core treats slashes as path separators, creating unintended segments.
     */
    private function sanitizeSlashesInSourceFields(ServerRequestInterface $request): ServerRequestInterface
    {
        /** @var array<string, mixed> $params */
        $params = $request->getParsedBody() ?? [];
        $tableName = (string)($params['tableName'] ?? '');
        $fieldName = (string)($params['fieldName'] ?? '');
        $fieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] ?? [];

        $fallbackCharacter = (string)($fieldConfig['generatorOptions']['fallbackCharacter'] ?? '-');
        $sourceFields = $fieldConfig['generatorOptions']['fields'] ?? [];

        /** @var array<string, mixed> $values */
        $values = $params['values'] ?? [];

        foreach ($sourceFields as $fieldNameParts) {
            if (is_string($fieldNameParts)) {
                $fieldNameParts = array_map('trim', explode(',', $fieldNameParts));
            }
            foreach ($fieldNameParts as $sourceField) {
                if (isset($values[$sourceField]) && is_string($values[$sourceField])) {
                    $values[$sourceField] = str_replace('/', $fallbackCharacter, $values[$sourceField]);
                }
            }
        }

        $params['values'] = $values;

        return $request->withParsedBody($params);
    }

    private function getOriginalSlug(ServerRequestInterface $request): string
    {
        /** @var array<string, mixed> $params */
        $params = $request->getParsedBody() ?? [];
        $tableName = (string)($params['tableName'] ?? '');
        $fieldName = (string)($params['fieldName'] ?? '');
        $fieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] ?? [];

        $slug = GeneralUtility::makeInstance(SlugHelper::class, $tableName, $fieldName, $fieldConfig);
        $mode = (string)($params['mode'] ?? '');
        /** @var array<string, mixed> $values */
        $values = $params['values'] ?? [];

        $values = array_map(static fn ($value) => is_string($value) ? $value : '', $values);

        return match ($mode) {
            'manual' => $slug->sanitize((string)($values['manual'] ?? '')),
            default => $slug->generate($values, (int)($params['parentPageId'] ?? 0)),
        };
    }
}
