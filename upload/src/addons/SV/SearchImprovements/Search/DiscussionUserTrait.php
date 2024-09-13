<?php

namespace SV\SearchImprovements\Search;

use XF\Mvc\Entity\Entity;

/**
 * @deprecated
 */
trait DiscussionUserTrait
{
    use DiscussionTrait;

    /**
     * @deprecated
     */
    protected function populateDiscussionUserMetaData(Entity $entity, array &$metaData): void
    {
        $this->populateDiscussionMetaData($entity, $metaData);
    }
}