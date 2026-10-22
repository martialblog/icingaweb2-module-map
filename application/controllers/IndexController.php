<?php

namespace Icinga\Module\Map\Controllers;

use Icinga\Module\Map\Common\ModuleConfig;
use Icinga\Module\Map\ProvidedHook\Icingadb\IcingadbSupport;

use Icinga\Application\Modules\Module;
use Icinga\Data\Filter\FilterException;
use Icinga\Util\Json;

use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Web\Compat\CompatController;

class IndexController extends CompatController
{
    // These options can be overwritten via URL parameters
    private const URL_OVERRIDABLE_PARAMS = [
        'cluster_problem_count',
        'default_lat',
        'default_long',
        'default_zoom',
        'disable_cluster_at_zoom',
        'max_native_zoom',
        'max_zoom',
        'min_zoom',
        'popup_mouseover',
        'stateType',
        'tile_url',
    ];

    /**
     * indexAction returns the main HTML element for the Map module.
     * This HTML element is then associated with Leaflet by the JavaScript section of this module.
     **/
    public function indexAction()
    {
        $config = ModuleConfig::getConfigWithDefaults();

        $map = null;
        $mapConfig = null;

        // Check if the URL Param 'load' is set and load config from the maps.ini
        if ($this->params->has("load")) {
            $map = $this->params->get("load");

            if (!preg_match("/^[\w]+$/", $map)) {
                throw new FilterException("Invalid character in map name. Allow characters: a-zA-Z0-9_");
            }

            $mapConfig = ModuleConfig::getMapsConfig();
            if (!$mapConfig->hasSection($map)) {
                throw new FilterException("Could not find stored map with name = " . $map);
            }
        }

        // Precedence for loading configuration:
        // 1. the module's configuration are the default
        // 2. The user's preferences
        // 3. A stored map in the maps.ini
        // 4. The URL parameters

        // Merge the user preferences into the default
        $userPreferences = $this->Auth()->getUser()->getPreferences();
        if ($userPreferences->has("map")) {
            $config->getSection("map")->merge($userPreferences->get("map"));
        }

        // TODO: Merge in the stored maps.ini config if exists

        // Then override with URL PARAMS

        // This will store the attributes we then attach to the map HTML element
        $mapAttributes = $config->toArray();

        // TODO: what does this param do and why?
        $showHost = $this->params->get("showHost");
        $mapAttributes['map_show_host'] = (! empty($showHost) ? preg_replace("/[\'\;]/", "", $showHost) : null);

        $mapAttributes['expand'] = (bool) $this->params->get('expand');

        // Check the URL parameters and override config if they are set
        // foreach (self::URL_OVERRIDABLE_PARAMS as $parameter) {
        //     if ($this->params->has($parameter)) {
        //         // If the parameter is present in the URL
        //         $this->view->$parameter = $this->params->get($parameter);
        //     } elseif (isset($map) && $mapConfig->getSection($map)->offsetExists($parameter)) {
        //         // If a maps.ini is set and has the given parameter
        //         $this->view->$parameter = $mapConfig->get($map, $parameter);
        //     } else {
        //         // Use the module config and its defaults as fallback
        //         $this->view->$parameter = $config->get("map", $parameter);
        //     }
        // }

        // if (!$this->view->disable_cluster_at_zoom) {
        //     $this->view->disable_cluster_at_zoom = $this->view->max_zoom - 1;
        // }

        // TODO What does this do and why?
        // $pattern = "/^([_]{0,1}(host|service))|\(|(object|state)Type/";
        // $urlParameters = $this->filterArray($this->params->toArray(false), $pattern);

        if (isset($map)) {
            $mapParameters = $this->filterArray($mapConfig->getSection($map)->toArray(), $pattern);
            $urlParameters = array_merge($mapParameters, $urlParameters);
        }

        // TODO: What?
        // array_walk($urlParameters, function (&$a, $b) {
        //     $a = $b . "=" . $a;
        // });

        // The map-script element holds the configuration for the module that
        // we need in the JavaScript part to render the map.
        $this->addContent(new HtmlElement('div', Attributes::create([
            'id' => 'map-script',
            'data-map-attrs' => Json::encode([
                'id' => uniqid(),
                'map_default_zoom' => !empty($this->view->default_zoom) ? (int) $this->view->default_zoom : null,
                'map_default_long' => !empty($this->view->default_long) ? preg_replace("/[^0-9\.\,\-]/", "", $this->view->default_long) : null,
                'map_default_lat' => !empty($this->view->default_lat) ? preg_replace("/[^0-9\.\,\-]/", "", $this->view->default_lat) : null,
                'map_max_zoom' => (int)$this->view->max_zoom,
                'map_max_native_zoom' => (int)$this->view->max_native_zoom,
                'map_min_zoom' => (int)$this->view->min_zoom,
                'disable_cluster_at_zoom' => (int) $this->view->disable_cluster_at_zoom,
                'tile_url' => preg_replace("/[\'\;]/", "", $this->view->tile_url),
                'cluster_problem_count' => (int) $this->view->cluster_problem_count,
                'popup_mouseover' => (int) $this->view->popup_mouseover,
                'map_show_host' => $mapAttributes['map_show_host'],
                'expand' => $mapAttributes['expand'],

                // TODO: I don't know what this does and why
                'url_parameters' => implode('&', $urlParameters),

                // TODO: Why use another attribute name here?
                'dashlet' => (bool) $this->view->compact,

                'isUsingIcingadb' => Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend(),
                'service_status' => [
                    0 => [$this->translate('OK', 'icinga.state'), 'OK'],
                    1 => [$this->translate('WARNING', 'icinga.state'), 'WARNING'],
                    2 => [$this->translate('CRITICAL', 'icinga.state'), 'CRITICAL'],
                    3 => [$this->translate('UNKNOWN', 'icinga.state'), 'UNKNOWN'],
                    99 => [$this->translate('PENDING', 'icinga.state'), 'PENDING']
                ],
                'translation' => [
                    'btn-zoom-in' => $this->translate('Zoom in'),
                    'btn-zoom-out' => $this->translate('Zoom out'),
                    'btn-dashboard' => $this->translate('Add to dashboard'),
                    'btn-fullscreen' => $this->translate('Fullscreen'),
                    'btn-default' => $this->translate('Show default view'),
                    'btn-locate' => $this->translate('Show current location'),
                    'host-down' => $this->translate('Host is down')
                ],
            ])
        ])));

        // The map element is the element we render the map in.
        // It can have the compact class for when it is rendered in a smaller space (e.g. dashbaord).
        $this->addContent(new HtmlElement('div', Attributes::create([
            'id' => 'map-' . $id,
            'class' => 'map' . ($this->view->compact ? ' compact' : '')
        ])));
    }

    private function filterArray(array $array, string $pattern): array
    {
        $matches = preg_grep($pattern, array_keys($array));
        return array_intersect_key($array, array_flip($matches));
    }
}
