<?php

namespace SV\SearchImprovements\Listener;

use XF\Mvc\RouteBuiltLink;
use XF\Mvc\Router;
use function strtolower;

abstract class LinkBuilder
{
    private function __construct() { }

    /** @var string|null */
    public static $contentType = null;

    public static function adminLinkBuilder(\SV\StandardLib\Repository\LinkBuilder $linkBuilder, Router $router): void
    {
        $linkBuilder->injectLinkBuilderCallback($router, 'enhanced-search', [self::class, 'injectContentTypeIntoLink']);
    }

    public static function publicLinkBuilder(\SV\StandardLib\Repository\LinkBuilder $linkBuilder, Router $router): void
    {
        $linkBuilder->injectLinkBuilderCallback($router, 'search', [self::class, 'fixQueryString']);
    }

    /**
     * @param string $prefix
     * @param array  $route
     * @param string $action
     * @param mixed  $data
     * @param array  $params
     * @param Router $router
     * @param bool   $suppressDefaultCallback
     * @return RouteBuiltLink|string|false|null
     * @noinspection PhpUnusedParameterInspection
     */
    public static function injectContentTypeIntoLink(string &$prefix, array &$route, string &$action, &$data, array &$params, Router $router, bool &$suppressDefaultCallback)
    {
        $action = strtolower($action);

        if (($action !== '' || !empty($params['reindex'])) &&
            $action !== 'indexes' &&
            self::$contentType !== '' && self::$contentType !== null &&
            empty($data['content_type']) && empty($params['content_type']))
        {
            $params['content_type'] = self::$contentType;
        }

        return null; // default XF processing
    }

    /**
     * @param string $prefix
     * @param array  $route
     * @param string $action
     * @param mixed  $data
     * @param array  $params
     * @param Router $router
     * @param bool   $suppressDefaultCallback
     * @return RouteBuiltLink|string|false|null
     * @noinspection PhpUnusedParameterInspection
     */
    public static function fixQueryString(string &$prefix, array &$route, string &$action, &$data, array &$params, Router $router, bool &$suppressDefaultCallback)
    {
        if ($data instanceof \XF\Entity\Search)
        {
            $params['q'] = $data->search_query;
            $params['t'] = $data->search_type;
            $params['c'] = $data->search_constraints;
            $params['o'] = $data->search_order;
            if ($data->search_grouping)
            {
                $params['g'] = 1;
            }

            // stop default build_callback usage, and use default XF processing
            $suppressDefaultCallback = true;
            return null;
        }

        return null; // default XF processing
    }
}