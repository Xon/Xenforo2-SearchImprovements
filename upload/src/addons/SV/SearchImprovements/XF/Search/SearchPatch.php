<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Repository\SpecializedSearchIndex;
use SV\SearchImprovements\Search\AbstractDataSourceExtractor;
use SV\SearchImprovements\Search\Specialized\SpecializedData;
use XF\Search\Data\AbstractData;
use XF\Search\Source\AbstractSource;
use function is_array, in_array, class_exists;

/**
 * Extends \XF\Search\Search
 */
class SearchPatch extends XFCP_SearchPatch
{
    /** @var string|null */
    public $specializedTypeFilter = null;
    /** @var bool */
    public $specializedIndexProxying = false;
    /** @var array<string,string> */
    protected $additionalTypes = [];
    /** @var array<string,SpecializedData|AbstractData> */
    protected $additionalHandlers = [];
    /** @var SpecializedSearchIndex|null */
    protected $specializedSearchIndexRepo = null;

    public function __construct(AbstractSource $source, array $types)
    {
        if (Globals::$shimSearchForSpecialization ?? false)
        {
            $this->specializedIndexProxying = true;
            $this->specializedSearchIndexRepo = \XF::repository('SV\SearchImprovements:SpecializedSearchIndex');
            $this->additionalTypes = $this->specializedSearchIndexRepo->getSearchHandlerDefinitions();
            $types = $types + $this->additionalTypes;
        }

        parent::__construct($source, $types);
    }

    public function isValidContentType($type): bool
    {
        if ($this->specializedTypeFilter !== null)
        {
            return $this->specializedTypeFilter === $type &&
                   isset($this->additionalTypes[$type]) &&
                   class_exists($this->additionalTypes[$type]);
        }

        $isValid = parent::isValidContentType($type);
        // ensure the special handler is loaded
        if ($isValid && isset($this->additionalTypes[$type]))
        {
            if (!$this->specializedIndexProxying)
            {
                return false;
            }

            $this->handler($type);
        }

        return $isValid;
    }

    public function handler($type)
    {
        if (isset($this->handlers[$type]))
        {
            return $this->handlers[$type];
        }

        if ($this->specializedIndexProxying && isset($this->additionalTypes[$type]))
        {
            $handler = $this->specializedSearchIndexRepo->getHandler($type);
            $this->additionalHandlers[$type] = $handler;
            $this->handlers[$type] = $handler;

            return $handler;
        }

        return parent::handler($type);
    }

    public function index($contentType, $entity, $deleteIfNeeded = true)
    {
        if ($this->specializedIndexProxying)
        {
            $handler = $this->handler($contentType);
            if ($handler instanceof SpecializedData)
            {
                /** @var AbstractData $handler */
                $source = AbstractDataSourceExtractor::getSearcher($handler);
                return $source->index($contentType, $entity, $deleteIfNeeded);
            }
        }

        return parent::index($contentType, $entity, $deleteIfNeeded);
    }

    public function delete($contentType, $del)
    {
        if ($this->specializedIndexProxying)
        {
            $handler = $this->handler($contentType);
            if ($handler instanceof SpecializedData)
            {
                /** @var AbstractData $handler */
                $source = AbstractDataSourceExtractor::getSearcher($handler);
                $source->delete($contentType, $del);

                return;
            }
        }

        parent::delete($contentType, $del);
    }

    public function enableBulkIndexing()
    {
        if ($this->specializedIndexProxying)
        {
            foreach ($this->additionalHandlers as $handler)
            {
                /** @var AbstractData $handler */
                $source = AbstractDataSourceExtractor::getSearcher($handler);
                $source->enableBulkIndexing();
            }
        }

        parent::enableBulkIndexing();
    }

    public function disableBulkIndexing()
    {
        if ($this->specializedIndexProxying)
        {
            foreach ($this->additionalHandlers as $handler)
            {
                $source = AbstractDataSourceExtractor::getSearcher($handler);
                $source->disableBulkIndexing();
            }
        }

        parent::disableBulkIndexing();
    }

    public function reassignContent($oldUserId, $newUserId)
    {
        if ($this->specializedIndexProxying)
        {
            foreach ($this->additionalTypes as $specializedType => $handlerClass)
            {
                $handler = $this->handler($specializedType);
                if ($handler->canReassignContent())
                {
                    $source = AbstractDataSourceExtractor::getSearcher($handler);
                    $source->reassignContent($oldUserId, $newUserId);
                }
            }
        }

        parent::reassignContent($oldUserId, $newUserId);
    }

    public function truncate($type = null)
    {
        if ($this->specializedIndexProxying)
        {
            // xf-rebuild:search calls with `truncate([])` before isValidContentType is called
            $type = $type === [] ? null : $type;
            $types = $type === null || is_array($type) ? $type : [$type];
            foreach ($this->additionalTypes as $specializedType => $handlerClass)
            {
                if ($types === null || in_array($specializedType, $types, true))
                {
                    $handler = $this->handler($specializedType);
                    $source = AbstractDataSourceExtractor::getSearcher($handler);
                    $source->truncate($specializedType);
                }
            }
        }

        return parent::truncate($type);
    }
}