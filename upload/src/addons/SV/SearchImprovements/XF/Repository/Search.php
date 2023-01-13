<?php

namespace SV\SearchImprovements\XF\Repository;

use SV\ElasticSearchEssentials\Globals;
use XF\Entity\Search as SearchEntity;
use function assert;

/**
 * Extends \XF\Repository\Search
 */
class Search extends XFCP_Search
{
    public function runSearch(\XF\Search\Query\KeywordQuery $query, array $constraints = [], $allowCached = true)
    {
        $search = parent::runSearch($query, $constraints, $allowCached);

        if ($search === null)
        {
            if (\XF::isAddOnActive('SV/ElasticSearchEssentials'))
            {
                Globals::$allowEmptyResults = true;
            }

            $search = $this->em->create('XF:Search');
            assert($search instanceof SearchEntity);
            $search->setupFromQuery($query, $constraints);
            $search->user_id = \XF::visitor()->user_id;
            $search->save();
        }

        return $search;
    }
}