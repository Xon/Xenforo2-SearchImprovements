<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\Search;

use SV\SearchImprovements\Globals;
use XF\Search\Query\Query;
use XFES\Elasticsearch\Exception as EsException;
use function count;
use function explode;
use function is_string;

trait ExecuteSearchWrapper
{
    protected function executeSearch(Query $query, array $dsl, $maxResults)
    {
        $logSearchDebugInfo = (Globals::$capturedSearchDebugInfo ?? null) !== null;
        if ($logSearchDebugInfo)
        {
            $index = explode(',', $this->es->getConfig()['index'] ?? '');
            if (count($index) > 1)
            {
                Globals::$capturedSearchDebugInfo['index'] = $index;
            }

            Globals::$capturedSearchDebugInfo['es_dsl'] = $dsl;
        }

        $matches = parent::executeSearch($query, $dsl, $maxResults);

        if ($logSearchDebugInfo)
        {
            foreach ($matches as $match)
            {
                // queries which have SQL constraints use numerical indexes, not strings
                $contentType = $match['content_type'] ?? $match[0] ?? null;
                if (!is_string($contentType))
                {
                    throw new \LogicException('Unknown return contents from '.__METHOD__);
                }
                if (!isset(Globals::$capturedSearchDebugInfo['summary'][$contentType]['raw']))
                {
                    Globals::$capturedSearchDebugInfo['summary'][$contentType]['raw'] = 0;
                }
                Globals::$capturedSearchDebugInfo['summary'][$contentType]['raw'] += 1;
            }
        }

        return $matches;
    }

    protected function logElasticsearchException(EsException $e, $errorPrefix = 'Elasticsearch error: ')
    {
        if ((Globals::$capturedSearchDebugInfo ?? null) !== null)
        {
            Globals::$capturedSearchDebugInfo['exception'] = $e->getMessage();
        }

        parent::logElasticsearchException($e, $errorPrefix);
    }
}