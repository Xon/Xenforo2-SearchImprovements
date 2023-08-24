<?php

namespace SV\SearchImprovements\Search\Features;

interface ISearchableDiscussionUser
{
    /**
     * @return array<int>
     */
    public function getDiscussionUserIds(): array;
}