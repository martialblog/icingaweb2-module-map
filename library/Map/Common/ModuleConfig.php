<?php

namespace Icinga\Module\Map\Common;

use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Authentication\Auth;

use Exception;

/**
 * ModuleConfig is a helper class to safely access this module's configuration.
 */
class ModuleConfig
{
    // Configuration defaults
    public const DEFAULTS = [
        'cluster_problem_count' => false,
        'default_lat' => '52.515855',
        'default_long' => '13.377485',
        'default_zoom' => 4,
        'max_native_zoom' => 19,
        'max_zoom' => 19,
        'min_zoom' => 2,
        'stateType' => 'soft',
        'popup_mouseover' => false,
        'tile_url' => '//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    ];

    /**
     * getMapsConfig returns the module's maps configuration.
     * The maps.ini can contain named overrides for various option.
     *
     * @return Config
     */
    public static function getMapsConfig(): Config
    {
        return Config::module('maps');
    }

    /**
     * getConfig returns the module's configuration.
     *
     * @return Config
     */
    public static function getConfig(): Config
    {
        return Config::module('map');
    }

    /**
     * getConfigWithDefaults loads all configuration options with their defaults.
     *
     * @return ?Config
     */
    public static function getConfigWithDefaults(?Config $moduleConfig = null): ?Config
    {
        // Try to load the configuration
        if ($moduleConfig === null) {
            try {
                $moduleConfig = self::getConfig();
                Logger::debug('Loaded Map module configuration');
            } catch (Exception $e) {
                Logger::error('Failed to load Map module configuration: %s', $e);
                return null;
            }
        }

        $disable_zoom_default = self::DEFAULTS['max_zoom'] - 1

        $config = [];

        $config['cluster_problem_count'] = (bool) $moduleConfig->get('map', 'cluster_problem_count', self::DEFAULTS['cluster_problem_count']);
        $config['default_lat'] = $moduleConfig->get('map', 'default_lat', self::DEFAULTS['default_lat']);
        $config['default_long'] = $moduleConfig->get('map', 'default_long', self::DEFAULTS['default_long']);
        $config['default_zoom'] = (int) $moduleConfig->get('map', 'default_zoom', self::DEFAULTS['default_zoom']);
        $config['max_native_zoom'] = (int) $moduleConfig->get('map', 'max_native_zoom', self::DEFAULTS['max_native_zoom']);
        $config['max_zoom'] = (int) $moduleConfig->get('map', 'max_zoom', self::DEFAULTS['max_zoom']);
        $config['min_zoom'] = (int) $moduleConfig->get('map', 'min_zoom', self::DEFAULTS['min_zoom']);
        $config['stateType'] = $moduleConfig->get('map', 'stateType', self::DEFAULTS['stateType']);
        $config['disable_cluster_at_zoom'] = (int) $moduleConfig->get('map', 'disable_cluster_at_zoom', $disable_zoom_default);
        $config['opencage_apikey'] = $moduleConfig->get('map', 'opencage_apikey', '');
        $config['popup_mouseover'] = (bool) $moduleConfig->get('map', 'popup_mouseover', self::DEFAULTS['popup_mouseover']);
        $config['tile_url'] = $moduleConfig->get('map', 'tile_url', self::DEFAULTS['tile_url']);

        return Config::fromArray($config);
    }
}
