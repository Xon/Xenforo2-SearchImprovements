<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use XF\Search\MetadataStructure;
use function array_column, array_filter, array_map, array_unique, count;

class Ticket extends XFCP_Ticket
{
    protected function getMetaData(\NF\Tickets\Entity\Ticket $ticket): array
    {
        $metaData = parent::getMetaData($ticket);

        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $userIds = array_column($ticket->getRelationFinder('Participants')->fetchColumns('user_id'), 'user_id');
            $userIds[] = $ticket->user_id;
            $metaData['discussion_user'] = array_unique(array_filter(array_map('\intval', $userIds)));
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