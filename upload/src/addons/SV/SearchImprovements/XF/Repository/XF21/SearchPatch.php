<?php

namespace SV\SearchImprovements\XF\Repository\XF21;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Entity\Search as SearchEntity;
use SV\SearchImprovements\XF\Repository\XFCP_SearchPatch;
use XF\PrintableException;
use function assert;
use function is_callable;

class SearchPatch extends XFCP_SearchPatch
{
    public function runSearch(\XF\Search\Query\Query $query, array $constraints = [], $allowCached = true)
    {
        if (\XF::options()->svShowSearchDebugInfo ?? '')
        {
            Globals::$capturedSearchDebugInfo = [];
        }
        try
        {
            $length = mb_strlen((string)$query->getKeywords());
            if ($length > 0)
            {
                $structure = $this->em->getEntityStructure('XF:Search');
                $maxLength = $structure->columns['search_query']['maxLength'] ?? -1;
                if ($maxLength > 0 && $length > $maxLength)
                {
                    $error = \XF::phrase('please_enter_value_using_x_characters_or_fewer', ['count' => $maxLength]);

                    if (is_callable([$query, 'setIsImpossibleQuery']))
                    {
                        $query->error('keywords', $error);
                        $query->setIsImpossibleQuery();
                    }
                    else
                    {
                        throw new PrintableException($error);
                    }
                }
            }

            $search = parent::runSearch($query, $constraints, $allowCached);

            if ($search === null)
            {
                $search = \XF::em()->create('XF:Search');
                assert($search instanceof SearchEntity);
                $search->setupFromQuery($query, $constraints);
                $search->user_id = \XF::visitor()->user_id;
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