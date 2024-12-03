<?php

namespace SV\SearchImprovements\XF\Entity\XF22;

use SV\SearchImprovements\XF\Entity\Search as ExtendedSearchEntity;
use SV\SearchImprovements\XF\Entity\XFCP_SearchPatch;
use SV\SearchImprovements\XF\Repository\Search as ExtendedSearchRepo;
use XF\Repository\Search as SearchRepo;
use SV\StandardLib\Helper;
use XF\Search\Query\KeywordQuery;

class SearchPatch extends XFCP_SearchPatch
{
    public function setupFromQuery(KeywordQuery $query, array $constraints = [])
    {
        parent::setupFromQuery($query, $constraints);

        /** @var ExtendedSearchEntity $this */
        // smooth over differences between member search & normal search
        // XF does falsy check on getGroupByType result :(
        $handler = $this->getContentHandler();
        if ($handler !== null && !$handler->getGroupByType())
        {
            /** @var ExtendedSearchRepo $searchRepo */
            $searchRepo = Helper::repository(SearchRepo::class);

            $searchType = $this->search_type;
            $firstChildType = $searchRepo->getChildContentTypeForContainerType($searchType);
            if ($firstChildType !== null && $firstChildType !== $searchType)
            {
                $constraints = $this->search_constraints;
                $constraints['content'] = $searchType;
                $this->search_constraints = $constraints;
                $this->search_type = $firstChildType;
            }
        }
    }
}