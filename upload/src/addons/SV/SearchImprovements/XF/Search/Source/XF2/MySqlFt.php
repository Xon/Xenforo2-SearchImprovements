<?php

namespace SV\SearchImprovements\XF\Search\Source\XF2;

use SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint;
use SV\SearchImprovements\XF\Search\Source\XFCP_MySqlFt;
use XF\Search\Query\Query;

/**
 * XF2.0/XF2.1 support
 */
class MySqlFt extends XFCP_MySqlFt
{
    /**
     * @param Query $query
     * @param       $maxResults
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
            if ($constraint instanceof RangeMetadataConstraint)
            {
                $sqlConstraint = $constraint->asSqlConstraint();
                if ($sqlConstraint)
                {
                    unset($constraints[$key]);
                    $query->withSql($sqlConstraint);
                }
            }
        }
        $query->setMetadataConstraints($constraints);

        /** @noinspection PhpParamsInspection */
        return parent::search($query, $maxResults);
    }
}
