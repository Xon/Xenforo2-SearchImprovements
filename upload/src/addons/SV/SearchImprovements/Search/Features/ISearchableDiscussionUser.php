<?php

namespace SV\SearchImprovements\Search\Features;

interface ISearchableDiscussionUser
{
    /**
     * @return array<int>
     */
    function getDiscussionUserIds(): array;
}