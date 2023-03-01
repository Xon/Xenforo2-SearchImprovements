<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Search\MetadataStructure;

class Ticket extends XFCP_Ticket
{
    protected static $svDiscussionEntity = \NF\Tickets\Entity\Ticket::class;
    use DiscussionTrait;

    protected function getMetaData(\NF\Tickets\Entity\Ticket $ticket): array
    {
        $metaData = parent::getMetaData($ticket);

        $this->populateDiscussionMetaData($ticket, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);

        $this->setupDiscussionMetadataStructure($structure);
    }
}