<?php

namespace SV\SearchImprovements\XF\Search\Source;

use SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint;
use XF\Search\Query\Query;

/**
 * Class MySqlFt
 *
 * @package SV\WordCountSearch\XF\Search\Source
 */
class MySqlFt extends XFCP_MySqlFt
{
    /**
     * @param Query $query
     * @param       $maxResults
     * @return array
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
        // XF\Search\Search & XF\Search\Query\Query aren't extendable
        $query->setMetadataConstraints($constraints);

        return parent::search($query, $maxResults);
    }
}
