<?php

namespace SV\SearchImprovements\NF\Tickets\Search\Data;

use SV\SearchImprovements\Repository\Search as SearchRepo;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\XF\Search\Query\Constraints\AndConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\PermissionConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\TypeConstraint;
use SV\StandardLib\Helper;
use XF\Http\Request;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;
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

    public function getTypePermissionConstraints(Query $query, $isOnlyType): array
    {
        $constraints = parent::getTypePermissionConstraints($query, $isOnlyType) ?? [];
        $repo = SearchRepo::get();
        if (!$repo->isPushingViewOtherChecksIntoSearch())
        {
            return $constraints;
        }

        // Node permissions are flat data, but the visibility status encodes hierarchical view data
        $nodePerms = Helper::perms()->getPerContentPermissions('nf_tickets_category');

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
            $userId = (int)\XF::visitor()->user_id;
            $viewConstraint = new OrConstraint(
                $userId === 0 ? null : new MetadataConstraint('discussion_user', $userId),
                new NotConstraint(new MetadataConstraint('ticketcat', $nonViewableNodeIds))
            );

            if ($isOnlyType)
            {
                // Note; ElasticSearchEssentials forces all getTypePermissionConstraints to have $isOnlyType=true as it knows how to compose multiple types together
                $constraints[] = $viewConstraint;
            }
            else
            {
                // XF constraints are AND'ed together for positive queries (ANY/ALL), and OR'ed for all negative queries (NONE).
                // PermissionConstraint forces the sub-query as a negative query instead of being part of the AND'ed positive queries
                $constraints[] = new PermissionConstraint(
                    new AndConstraint(
                        new TypeConstraint(...$this->getSearchableContentTypes()),
                        new NotConstraint($viewConstraint)
                    )
                );
            }
        }

        return $constraints;
    }

    public function applyTypeConstraintsFromInput(Query $query, Request $request, array &$urlConstraints)
    {
        $constraints = $request->filter([
            'c.participants' => 'str',

            'c.replies.lower' => 'uint',
            'c.replies.upper' => '?uint,empty-str-to-null',
        ]);

        $repo = SearchRepo::get();
        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.participants', 'discussion_user'
        );
        if ($repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.replies.lower', 'c.replies.upper','replies',
            [$this->getTicketQueryTableReference()]))
        {
            $request->set('c.min_reply_count', 0);
        }

        parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function getTicketQueryTableReference()
    {
        return new TableReference(
            'ticket',
            'xf_nf_tickets_ticket',
            'ticket.ticket_id = search_index.discussion_id'
        );
    }
}