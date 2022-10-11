<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\PermissionCache;
use SV\SearchImprovements\Search\DiscussionUserTrait;
use SV\SearchImprovements\XF\Search\Query\Constraints\ExistsConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use function count;

class Message extends XFCP_Message
{
    use DiscussionUserTrait;

    protected function getMetaData(\NF\Tickets\Entity\Message $entity): array
    {
        $metaData = parent::getMetaData($entity);

        $this->populateDiscussionUserMetaData($entity->Ticket, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);

        if (Globals::isPushingViewOtherChecksIntoSearch())
        {
            $structure->addField('discussion_user', MetadataStructure::INT);
            $this->setupDiscussionUserMetadataStructure($structure);
        }
    }

    public function getTypePermissionConstraints(\XF\Search\Query\Query $query, $isOnlyType): array
    {
        $constraints = parent::getTypePermissionConstraints($query, $isOnlyType) ?? [];
        if (!Globals::isPushingViewOtherChecksIntoSearch())
        {
            return $constraints;
        }

        // Node permissions are flat data, but the visibility status encodes hierarchical view data
        $nodePerms = PermissionCache::getPerms('ticket', 'nf_tickets_category');

        $nonViewableNodeIds = [];
        foreach($nodePerms as $nodeId => $perm)
        {
            if (count($perm) > 1)
            {
                // view check is done in parent::getTypePermissionConstraints
                if (!empty($perm['view']) && empty($perm['viewOthers']))
                {
                    $nonViewableNodeIds[] = $nodeId;
                }
            }
        }

        if (count($nonViewableNodeIds) !== 0)
        {
            // Note; ElasticSearchEssentials forces all getTypePermissionConstraints to have $isOnlyType=true as it knows how to compose multiple types together
            $userId = \XF::visitor()->user_id;
            $constraints[] = new OrConstraint(
                $isOnlyType ? null : new NotConstraint(new ExistsConstraint('ticketcat')),
                $userId === 0 ? null : new MetadataConstraint('discussion_user', $userId),
                new NotConstraint(new MetadataConstraint('ticketcat', $nonViewableNodeIds))
            );
        }

        return $constraints;
    }
}