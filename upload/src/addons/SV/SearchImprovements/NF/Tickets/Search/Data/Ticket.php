<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use XF\Search\MetadataStructure;
use function array_unique;

class Ticket extends XFCP_Ticket
{
    protected function getMetaData(\NF\Tickets\Entity\Ticket $ticket): array
    {
        $metaData = parent::getMetaData($ticket);

        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $metaData['discussion_user'] = $ticket->user_id;
        }

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);
        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $structure->addField('discussion_user', MetadataStructure::INT);
        }
    }
}