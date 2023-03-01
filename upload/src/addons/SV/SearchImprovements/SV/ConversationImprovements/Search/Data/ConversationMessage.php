<?php

namespace SV\SearchImprovements\SV\ConversationImprovements\Search\Data;

use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Search\MetadataStructure;

/**
 * Extends \SV\ConversationImprovements\Search\Data\ConversationMessage
 */
class ConversationMessage extends XFCP_ConversationMessage
{
    protected static $svDiscussionEntity = \XF\Entity\ConversationMaster::class;
    use DiscussionTrait;

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function getMetadata(\XF\Entity\ConversationMessage $entity)
    {
        $metaData = parent::getMetadata($entity);

        $this->populateDiscussionMetaData($entity->Conversation, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        parent::setupMetadataStructure($structure);

        $this->setupDiscussionMetadataStructure($structure);
    }
}