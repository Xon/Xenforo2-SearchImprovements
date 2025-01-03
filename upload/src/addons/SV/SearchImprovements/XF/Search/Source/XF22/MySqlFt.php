<?php

namespace SV\SearchImprovements\XF\Search\Source\XF22;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Search\Query\Constraints\AbstractConstraint;
use XF\Search\Query\KeywordQuery;
use SV\SearchImprovements\XF\Search\Source\XFCP_MySqlFt;
use function end;

class MySqlFt extends XFCP_MySqlFt
{
    /**
     * @param KeywordQuery $query
     * @param int          $maxResults
     * @return array
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function search(KeywordQuery $query, $maxResults)
    {
        /** @var \SV\SearchImprovements\XF\Search\Query\KeywordQuery $query */
        $query = clone $query; // do not allow others to see our manipulation for the query object
        // rewrite metadata range queries into search_index queries
        $constraints = $query->getMetadataConstraints();
        foreach ($constraints as $key => $constraint)
        {
            if ($constraint instanceof AbstractConstraint)
            {
                unset($constraints[$key]);
                $sqlConstraints = $constraint->asSqlConstraint();
                foreach ($sqlConstraints as $sqlConstraint)
                {
                    $query->withSql($sqlConstraint);
                }
            }
        }
        $query->setMetadataConstraints($constraints);

        $db = \XF::db();
        $wasLoggingQueries = false;
        $logSearchDebugInfo = (Globals::$capturedSearchDebugInfo ?? null) !== null;
        if ($logSearchDebugInfo)
        {
            $wasLoggingQueries = $db->areQueriesLogged();
            $db->logQueries(true, false);
        }
        try
        {
            return parent::search($query, $maxResults);
        }
        finally
        {
            if ($logSearchDebugInfo)
            {
                $queryLog = $db->getQueryLog();
                $lastQuery = end($queryLog);
                if ($lastQuery !== false)
                {
                    Globals::$capturedSearchDebugInfo['mysql_dsl'] = $lastQuery['query'] ?? '';
                    Globals::$capturedSearchDebugInfo['mysql_params'] = $lastQuery['params'] ?? [];
                }

                $db->logQueries($wasLoggingQueries);
            }
        }
    }
}
