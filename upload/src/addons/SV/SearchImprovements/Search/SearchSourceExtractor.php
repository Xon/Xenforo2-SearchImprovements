<?php

namespace SV\SearchImprovements\Search;

use XF\Search\Search;
use XF\Search\Source\AbstractSource;

abstract class SearchSourceExtractor extends Search
{
    public static function getSource(Search $search): AbstractSource
    {
        return $search->source;
    }
}