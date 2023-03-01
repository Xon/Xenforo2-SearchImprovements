<?php

namespace SV\SearchImprovements\Search;

/**
 * @deprecated
 */
trait DiscussionUserTrait
{
    use DiscussionTrait;

    /**
     * @deprecated
     */
    protected function populateDiscussionUserMetaData(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {
        $this->populateDiscussionMetaData($entity, $metaData);
    }
}