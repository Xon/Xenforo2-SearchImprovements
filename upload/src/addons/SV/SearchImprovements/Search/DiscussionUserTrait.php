<?php

namespace SV\SearchImprovements\Search;

use SV\SearchImprovements\Globals;
use XF\Search\MetadataStructure;

trait DiscussionUserTrait
{
    protected function populateDiscussionUserMetaData(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {
        if (Globals::isPushingViewOtherChecksIntoSearch())
        {
            $this->setupDiscussionUserMetadata($entity, $metaData);

            if (isset($entity->discussion_user_ids))
            {
                /** @var int[] $userIds */
                $userIds = $entity->discussion_user_ids;
            }
            else if (isset($entity->user_id))
            {
                /** @var int[] $userIds */
                $userIds = [$entity->user_id];
            }
            else
            {
                $userIds = [];
            }

            if (count($userIds) !== 0)
            {
                $metaData['discussion_user'] = $userIds;
            }
        }
    }

    protected function setupDiscussionUserMetadata(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {

    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        /** @noinspection PhpMultipleClassDeclarationsInspection */
        parent::setupMetadataStructure($structure);

        if (Globals::isPushingViewOtherChecksIntoSearch())
        {
            $structure->addField('discussion_user', MetadataStructure::INT);
            $this->setupDiscussionUserMetadataStructure($structure);
        }
    }

    protected function setupDiscussionUserMetadataStructure(MetadataStructure $structure): void
    {

    }
}