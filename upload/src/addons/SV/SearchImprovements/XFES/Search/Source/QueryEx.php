<?php

namespace SV\SearchImprovements\XFES\Search\Source;

use XF\Search\Query\Query;

class QueryEx extends Query
{
    public static function setSort($query, $sort, $sortName)
    {
        $query->order = $sort;
        $query->orderName = $sortName;
    }
}