<?php

namespace SV\SearchImprovements\Search;

use SV\SearchImprovements\Globals;
use XF\Search\MetadataStructure;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

trait DiscussionUserTrait
{
    protected function populateDiscussionUserMetaData(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {
        if (Globals::isPushingViewOtherChecksIntoSearch())
        {
            $this->setupDiscussionUserMetadata($entity, $metaData);

            if (isset($entity->discussion_user_ids))
            {
                $userIds = $entity->discussion_user_ids;
            }
            else if (isset($entity->user_id))
            {
                $userId = (int)$entity->user_id;
                $userIds = $userId !== 0 ? [$userId] : [];
            }
            else
            {
                $userIds = [];
            }

            // ensure consistent behavior that it is an array of ints, and no zero user ids are sent to XFES
            /** @var int[] $userIds */
            $userIds = array_filter(array_map('\intval', $userIds), function (int $i) {
                return $i !== 0;
            });
            if (count($userIds) !== 0)
            {
                // array_values ensures the value is encoded as a json array, and not a json hash if the php array is not a list
                $metaData['discussion_user'] = array_values(array_unique($userIds));
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