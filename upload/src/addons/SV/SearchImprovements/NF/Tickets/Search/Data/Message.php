<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\XF\Search\Query\Constraints\ExistsConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use SV\StandardLib\Helper;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use function count;
use function is_array;

class Message extends XFCP_Message
{
    protected static $svDiscussionEntity = \NF\Tickets\Entity\Ticket::class;
    use DiscussionTrait;

    protected function getMetaData(\NF\Tickets\Entity\Message $entity): array
    {
        $metaData = parent::getMetaData($entity);

        $this->populateDiscussionMetaData($entity->Ticket, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);

        $this->setupDiscussionMetadataStructure($structure);
    }

    public function getTypePermissionConstraints(\XF\Search\Query\Query $query, $isOnlyType): array
    {
        $constraints = parent::getTypePermissionConstraints($query, $isOnlyType) ?? [];
        $repo = Globals::repo();
        if (!$repo->isPushingViewOtherChecksIntoSearch())
        {
            return $constraints;
        }

        // Node permissions are flat data, but the visibility status encodes hierarchical view data
        $nodePerms = Helper::perms()->getContentPermissions('ticket', 'nf_tickets_category');

        $nonViewableNodeIds = [];
        foreach ($nodePerms as $nodeId => $perm)
        {
            // The permission cache may not be an array, which implies the view/viewXXX will all be false and the parent's view check should have failed.
            // For categories, XF only persists the 'view' attribute into the permission cache, and it will not have threads.
            // If this is a non-category node, there will be a number of permissions.
            if (is_array($perm) && count($perm) > 1)
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

    public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $repo = Globals::repo();
        if ($repo->applyRepliesConstraint($query, $request,
            function () use (&$urlConstraints) {
                unset($urlConstraints['replies']['upper']);
            }, function () use (&$urlConstraints) {
                unset($urlConstraints['replies']['lower']);
            }, [$this->getTicketQueryTableReference()]))
        {
            $request->set('c.min_reply_count', 0);
        }

        parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function getTicketQueryTableReference()
    {
        return new \XF\Search\Query\TableReference(
            'ticket',
            'xf_nf_tickets_ticket',
            'ticket.ticket_id = search_index.discussion_id'
        );
    }
}