<?php

namespace SV\SearchImprovements\Search;

use XF\Search\Data\AbstractData;
use XF\Search\Search;

abstract class AbstractDataSourceExtractor extends AbstractData
{
    public static function getSearcher(AbstractData $handler): Search
    {
        return $handler->searcher;
    }
}