<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Configuration
 *
 * @package Wazum\Sluggi\Helper
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class Configuration
{
    /**
     * @param string $key
     * @return mixed|null
     */
    public static function get(string $key)
    {
        $value = null;
        try {
            $value = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(
                'sluggi',
                $key
            );
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
        } catch
        (ExtensionConfigurationPathDoesNotExistException $e) {
        }

        return $value;
    }
}
