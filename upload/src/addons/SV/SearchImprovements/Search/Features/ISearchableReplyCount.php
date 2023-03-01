<?php

namespace SV\SearchImprovements\Search\Features;

interface ISearchableReplyCount
{
    public function getReplyCountForSearch(): int;
}