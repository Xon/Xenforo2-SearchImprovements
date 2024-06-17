<?php

namespace SV\SearchImprovements\XF\Entity\XF22;

use SV\SearchImprovements\XF\Entity\Search;
use SV\SearchImprovements\XF\Entity\XFCP_Search;
use SV\SearchImprovements\XF\Repository\Search as SearchRepo;
use function assert;

class SearchPatch extends XFCP_Search
{
    public function setupFromQuery(\XF\Search\Query\KeywordQuery $query, array $constraints = [])
    {
        parent::setupFromQuery($query, $constraints);

        /** @var Search $this */
        // smooth over differences between member search & normal search
        // XF does falsy check on getGroupByType result :(
        $handler = $this->getContentHandler();
        if ($handler !== null && !$handler->getGroupByType())
        {
            $searchRepo = $this->repository('XF:Search');
            assert($searchRepo instanceof SearchRepo);

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