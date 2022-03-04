<?php

namespace SV\SearchImprovements\Search;

use XF\Search\Data\AbstractData;

abstract class AbstractDataSourceExtractor extends AbstractData
{
    public static function getSearcher(AbstractData $handler): \XF\Search\Search
    {
        return $handler->searcher;
    }
}