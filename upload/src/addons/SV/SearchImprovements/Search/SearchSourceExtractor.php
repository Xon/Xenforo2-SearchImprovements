<?php

namespace SV\SearchImprovements\Search;

class SearchSourceExtractor extends \XF\Search\Search
{
    public static function getSource(\XF\Search\Search $search): \XF\Search\Source\AbstractSource
    {
        return $search->source;
    }
}