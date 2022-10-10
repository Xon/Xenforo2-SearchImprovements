<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use SV\SearchImprovements\Search\DiscussionUserTrait;
use XF\Search\MetadataStructure;

class Ticket extends XFCP_Ticket
{
    use DiscussionUserTrait;

    protected function getMetaData(\NF\Tickets\Entity\Ticket $ticket): array
    {
        $metaData = parent::getMetaData($ticket);

        $this->populateDiscussionUserMetaData($ticket, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);

        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $structure->addField('discussion_user', MetadataStructure::INT);
            $this->setupDiscussionUserMetadataStructure($structure);
        }
    }
}