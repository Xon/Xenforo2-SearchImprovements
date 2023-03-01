<?php

namespace SV\SearchImprovements\SV\ConversationImprovements\Search\Data;

use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Search\MetadataStructure;

/**
 * Extends \SV\ConversationImprovements\Search\Data\Conversation
 */
class Conversation extends XFCP_Conversation
{
    protected static $svDiscussionEntity = \XF\Entity\ConversationMaster::class;
    use DiscussionTrait;

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function getMetadata(\XF\Entity\ConversationMaster $conversation)
    {
        $metaData = parent::getMetadata($conversation);

        $this->populateDiscussionMetaData($conversation, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        parent::setupMetadataStructure($structure);

        $this->setupDiscussionMetadataStructure($structure);
    }
}