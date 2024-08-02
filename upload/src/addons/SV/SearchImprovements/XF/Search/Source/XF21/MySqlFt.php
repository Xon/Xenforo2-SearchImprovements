<?php

namespace SV\SearchImprovements\XF\Search\Source\XF21;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Search\Query\Constraints\AbstractConstraint;
use SV\SearchImprovements\XF\Search\Source\XFCP_MySqlFt;
use XF\Search\Query\Query;
use XF\Search\Query\SqlConstraint;
use function end;
use function is_array;

/**
 * XF2.1 support
 */
class MySqlFt extends XFCP_MySqlFt
{
    /**
     * @param Query $query
     * @param int   $maxResults
     * @return array
     * @noinspection DuplicatedCode
     * @noinspection PhpHierarchyChecksInspection
     * @noinspection PhpSignatureMismatchDuringInheritanceInspection
     */
    public function search(Query $query, $maxResults)
    {
        /** @var \SV\SearchImprovements\XF\Search\Query\Query $query */
        $query = clone $query; // do not allow others to see our manipulation for the query object
        // rewrite metadata range queries into search_index queries
        $constraints = $query->getMetadataConstraints();
        foreach ($constraints as $key => $constraint)
        {
            if ($constraint instanceof AbstractConstraint)
            {
                unset($constraints[$key]);
                $sqlConstraint = $constraint->asSqlConstraint();
                if (is_array($sqlConstraint))
                {
                    $sqlConstraints = $sqlConstraint;
                    foreach ($sqlConstraints as $sqlConstraint)
                    {
                        $query->withSql($sqlConstraint);
                    }
                }
                else if ($sqlConstraint instanceof SqlConstraint)
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
