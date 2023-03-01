<?php

namespace SV\SearchImprovements\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class LinkBuilder
 *
 * @package SV\ReportCentreEssentials\Repository
 */
class LinkBuilder extends Repository
{
    /**
     * @var bool
     */
    protected $init = false;

    /**
     * @var array
     */
    protected $previousCallbacks = [];

    /** @var string|null */
    protected $contentType;

    public function setContentType(string $contentType)
    {
        $this->contentType = $contentType;
    }

    public function hookRouteBuilder()
    {
        if ($this->init)
        {
            return;
        }
        $this->init = true;
        // patch routing on the fly, this way upgrades to XF don't break this
        $router = \XF::app()->router('admin');
        $routes = $router->getRoutes();
        // must be an array, and not a closure :(
        $callable = [$this, 'injectContentTypeIntoLink'];
        foreach (['enhanced-search'] as $routeLabel)
        {
            if (empty($routes[$routeLabel]))
            {
                continue;
            }

            foreach ($routes[$routeLabel] as $subSection => $route)
            {
                // chainable callbacks. what a hack
                if (!empty($route['build_callback']))
                {
                    $previousCallback = $route['build_callback'];
                    if (\is_array($previousCallback))
                    {
                        $previousCallback = \Closure::fromCallable($previousCallback);
                    }
                    $this->previousCallbacks[$subSection] = $previousCallback;
                }
                $route['context'] = empty($route['context']) ? '' : $route['context'];
                $route['subSection'] = $subSection;
                $route['build_callback'] = $callable;
                $router->addRoute($routeLabel, $subSection, $route);
            }
        }
    }

    /**
     * @param string         $prefix
     * @param array          $route
     * @param string         $action
     * @param mixed          $data
     * @param array          $params
     * @param \XF\Mvc\Router $router
     * @return \XF\Mvc\RouteBuiltLink|null
     */
    public function injectContentTypeIntoLink(
        string &$prefix,
        array &$route,
        string &$action,
        &$data,
        array &$params,
        \XF\Mvc\Router $router
    ): ?\XF\Mvc\RouteBuiltLink
    {
        $action = \strtolower($action);

        if (($action !== '' || !empty($params['reindex'])) &&
            $action !== 'indexes' &&
            $this->contentType !== '' && $this->contentType !== null &&
            empty($data['content_type']) && empty($params['content_type']))
        {
            $params['content_type'] = $this->contentType;
        }

        // chain callbacks
        if (isset($route['subSection'], $this->previousCallbacks[$route['subSection']]))
        {
            $callable = $this->previousCallbacks[$route['subSection']];

            return \call_user_func_array(
                $callable,
                [&$prefix, &$route, &$action, &$data, &$params, $router]
            );
        }

        return null;
    }
}
