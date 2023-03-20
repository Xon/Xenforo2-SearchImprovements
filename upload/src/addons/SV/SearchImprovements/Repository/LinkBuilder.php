<?php

namespace SV\SearchImprovements\Repository;

use XF\Mvc\Entity\Repository;
use XF\Mvc\Router;

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
     * @var array<callable>
     */
    protected $previousCallbacks = [];

    /** @var string|null */
    protected $contentType;

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function hookSearchQueryBuilder(): void
    {
        $this->injectLinkBuilderCallback(\XF::app()->router('public'), ['search'], [$this, 'fixQueryString']);
    }

    public function hookRouteBuilder(): void
    {
        if ($this->init)
        {
            return;
        }
        $this->init = true;
        // patch routing on the fly, this way upgrades to XF don't break this
        $this->injectLinkBuilderCallback(\XF::app()->router('admin'), ['enhanced-search'], [$this, 'injectContentTypeIntoLink']);
    }

    /**
     * @param Router                     $router
     * @param string[]                   $sections
     * @param array{0: string, 1:string} $callable
     * @return void
     */
    protected function injectLinkBuilderCallback(\XF\Mvc\Router $router, array $sections, array $callable): void
    {
        $routes = $router->getRoutes();
        foreach ($sections as $routeLabel)
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
     * @return \XF\Mvc\RouteBuiltLink|string|null
     */
    public function injectContentTypeIntoLink(
        string &$prefix,
        array &$route,
        string &$action,
        &$data,
        array &$params,
        \XF\Mvc\Router $router
    )
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
        $callable = $this->previousCallbacks[$route['subSection']] ?? null;
        if ($callable !== null)
        {
            return \call_user_func_array(
                $callable,
                [&$prefix, &$route, &$action, &$data, &$params, $router]
            );
        }

        return null;
    }

    /**
     * @param string         $prefix
     * @param array          $route
     * @param string         $action
     * @param mixed          $data
     * @param array          $params
     * @param \XF\Mvc\Router $router
     * @return \XF\Mvc\RouteBuiltLink|string|null
     */
    public function fixQueryString(
        string &$prefix,
        array &$route,
        string &$action,
               &$data,
        array &$params,
        \XF\Mvc\Router $router
    )
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

            $params = array_filter($params, function ($e) {
                // avoid falsy, which may include terms we don't want to skip
                return $e !== null && $e !== 0 && $e !== '' && $e !== [];
            });

            return null;
        }

        // chain callbacks
        $callable = $this->previousCallbacks[$route['subSection']] ?? null;
        if ($callable !== null)
        {
            return \call_user_func_array(
                $callable,
                [&$prefix, &$route, &$action, &$data, &$params, $router]
            );
        }

        return null; // default processing otherwise
    }
}
