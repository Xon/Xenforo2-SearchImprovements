<?php

namespace SV\SearchImprovements\XF\Repository\XF21;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Entity\Search as ExtendedSearchEntity;
use SV\SearchImprovements\XF\Repository\XFCP_SearchPatch;
use SV\StandardLib\Helper;
use XF\Entity\Search as SearchEntity;
use XF\PrintableException;
use XF\Search\Query\Query;
use function is_callable;

class SearchPatch extends XFCP_SearchPatch
{
    /**
     * @noinspection PhpSignatureMismatchDuringInheritanceInspection
     * @noinspection PhpParamsInspection
     */
    public function runSearch(Query $query, array $constraints = [], $allowCached = true)
    {
        if (\XF::options()->svShowSearchDebugInfo ?? '')
        {
            Globals::$capturedSearchDebugInfo = [];
        }
        try
        {
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $length = mb_strlen((string)$query->getKeywords());
            if ($length > 0)
            {
                $structure = Helper::getEntityStructure(SearchEntity::class);
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

            /** @noinspection PhpParamsInspection */
            $search = parent::runSearch($query, $constraints, $allowCached);

            if ($search === null)
            {
                /** @var ExtendedSearchEntity $search */
                $search = Helper::createEntity(SearchEntity::class);
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