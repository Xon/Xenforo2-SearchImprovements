<?php

namespace SV\SearchImprovements\XF\Repository;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Entity\Search as SearchEntity;
use function array_diff;
use function array_merge_recursive;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function reset;

/**
 * Extends \XF\Repository\Search
 */
class Search extends XFCP_Search
{
    public function getContainerTypeForContentType(string $contentType): ?string
    {
        if ($contentType === '')
        {
            return null;
        }

        try
        {
            $handler = $this->app()->search()->handler($contentType);
        }
        catch (\Throwable $e)
        {
            return null;
        }

        return $handler->getGroupByType();
    }

    public function getChildContentTypeForContainerType(string $containerContentType): ?string
    {
        // XF lacks a link from the parent type to child types :`(
        $search = $this->app()->search();
        foreach ($this->app()->getContentTypeField('search_handler_class') as $contentType => $handlerClass)
        {
            if ($containerContentType === $contentType)
            {
                continue;
            }

            $handler = $search->handler($contentType);
            if ($handler !== null)
            {
                $childTypes = $handler->getSearchableContentTypes();
                assert(is_array($childTypes));
                if (count($childTypes) > 1 && in_array($containerContentType, $childTypes, true))
                {
                    $childTypes = array_diff($childTypes, [$containerContentType]);
                    if (count($childTypes) === 0)
                    {
                        return null;
                    }

                    return reset($childTypes);
                }
            }
        }

        return null;
    }

    protected function getSearchDebugRequestStateSnapshot(): array
    {
        $debug = [
            'time' => round(microtime(true) - $this->app()->container('time.granular'), 4),
            'queries' => $this->db()->getQueryCount(),
            'memory' => round(memory_get_peak_usage() / 1024 / 1024, 2)
        ];

        if (\XF::isAddOnActive('SV/RedisCache'))
        {
            $mainConfig = \XF::app()->config()['cache'];
            $contexts = [];
            $contexts[''] = $mainConfig;
            if (isset($mainConfig['context']))
            {
                $contexts = $contexts + $mainConfig['context'];
            }
            foreach ($contexts as $contextLabel => $config)
            {
                $cache = \XF::app()->cache($contextLabel, false);
                if ($cache instanceof \SV\RedisCache\Redis)
                {
                    $stats = $cache->getRedisStats();
                    if (!isset($debug['cache']['get'], $debug['cache']['set']))
                    {
                        $debug['cache']['get'] = 0;
                        $debug['cache']['set'] = 0;
                    }
                    $debug['cache']['get'] += (int)($stats['gets'] ?? 0);
                    $debug['cache']['set'] += (int)($stats['sets'] ?? 0);
                }
            }
        }

        return $debug;
    }

    protected function getSearchDebugSummary(SearchEntity $search): array
    {
        $arr = [
            'summary' => [],
        ];

        foreach ($search->search_results as $match)
        {
            $contentType = $match[0] ?? null;
            if (!is_string($contentType))
            {
                throw new \LogicException('Unknown return contents from Search::search_results');
            }
            if (!isset($arr['summary'][$contentType]['php']))
            {
                $arr['summary'][$contentType]['php'] = 0;
            }
            $arr['summary'][$contentType]['php'] += 1;
        }

        return $arr;
    }

    public function runSearch(\XF\Search\Query\KeywordQuery $query, array $constraints = [], $allowCached = true)
    {
        if (\XF::options()->svShowSearchDebugInfo ?? '')
        {
            Globals::$capturedSearchDebugInfo = [];
        }
        try
        {
            $search = parent::runSearch($query, $constraints, $allowCached);

            // re-used search, don't bother updating the search debug info
            if ($search !== null && $search->search_date < \XF::$time)
            {
                return $search;
            }

            if ($search === null)
            {
                $search = $this->em->create('XF:Search');
                assert($search instanceof SearchEntity);
                $search->setupFromQuery($query, $constraints);
                $search->user_id = \XF::visitor()->user_id;
            }
            assert($search instanceof SearchEntity);

            $capturedSearchDebugInfo = Globals::$capturedSearchDebugInfo ?? [];
            // convert empty array to null
            if ($capturedSearchDebugInfo !== [])
            {
                $capturedSearchDebugInfo = array_merge_recursive(
                    $capturedSearchDebugInfo,
                    $this->getSearchDebugRequestStateSnapshot(),
                    $this->getSearchDebugSummary($search)
                );

                $search->sv_debug_info = $capturedSearchDebugInfo;
                $search->save();
            }

            return $search;
        }
        finally
        {
            Globals::$capturedSearchDebugInfo = null;
        }
    }
}