<?php

namespace SV\SearchImprovements\XF\Search\Source\XF22;

use SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint;
use SV\SearchImprovements\XF\Search\Source\XFCP_MySqlFt;
use XF\Search\Query\KeywordQuery;

/**
 * XF2.2+ support
 */
class MySqlFt extends XFCP_MySqlFt
{
    /**
     * @param KeywordQuery $query
     * @param int          $maxResults
     * @return array
     * @noinspection DuplicatedCode
     */
    public function search(KeywordQuery $query, $maxResults)
    {
        /** @var \SV\SearchImprovements\XF\Search\Query\KeywordQuery $query */
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

        return parent::search($query, $maxResults);
    }
}
