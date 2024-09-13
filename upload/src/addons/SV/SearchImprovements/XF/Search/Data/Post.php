<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use SV\SearchImprovements\Repository\Search as SearchRepo;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\XF\Search\Query\Constraints\AndConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\ExistsConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\PermissionConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\TypeConstraint;
use SV\StandardLib\Helper;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use function count;
use function is_array;

class Post extends XFCP_Post
{
    protected static $svDiscussionEntity = ThreadEntity::class;
    use DiscussionTrait;

    protected function getMetaData(\XF\Entity\Post $entity)
    {
        $metaData = parent::getMetaData($entity);

        $this->populateDiscussionMetaData($entity->Thread, $metaData);

        return $metaData;
    }

    protected function setupDiscussionUserMetadata(Entity $entity, array &$metaData): void
    {
        /** @var ThreadEntity $entity */
        if (Helper::isAddOnActive('SV/ViewStickyThreads'))
        {
            if ($entity->sticky ?? false)
            {
                $metaData['sticky'] = true;
            }
        }
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        parent::setupMetadataStructure($structure);

        $this->setupDiscussionMetadataStructure($structure);
    }

    protected function setupDiscussionUserMetadataStructure(MetadataStructure $structure): void
    {
        if (Helper::isAddOnActive('SV/ViewStickyThreads'))
        {
            $structure->addField('sticky', MetadataStructure::BOOL);
        }
    }

    public function getTypePermissionConstraints(Query $query, $isOnlyType)
    {
        $constraints = parent::getTypePermissionConstraints($query, $isOnlyType) ?? [];
        // These are only meaningful with ElasticSearch+XenForo Enhanced Search
        $repo = SearchRepo::get();
        if (!$repo->isPushingViewOtherChecksIntoSearch())
        {
            return $constraints;
        }

        // Node permissions are flat data, but the visibility status encodes hierarchical view data
        $nodePerms = Helper::perms()->getPerContentPermissions('node');

        $nonViewableNodeIds = $viewableStickiesNodeIds = [];
        $viewStickies = Helper::isAddOnActive('SV/ViewStickyThreads');
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
                    if ($viewStickies && !empty($perm['viewStickies']) && !empty($perm['viewContent']))
                    {
                        $viewableStickiesNodeIds[] = $nodeId;
                    }
                }
            }
        }

        if (count($nonViewableNodeIds) !== 0 || count($viewableStickiesNodeIds) !== 0)
        {
            $userId = (int)\XF::visitor()->user_id;
            $viewConstraint = new OrConstraint(
                $userId === 0 ? null : new MetadataConstraint('discussion_user', $userId),
                (count($viewableStickiesNodeIds) === 0
                    ? null
                    : new AndConstraint(
                        new ExistsConstraint('sticky'),
                        new MetadataConstraint('node', $viewableStickiesNodeIds)
                    )),
                (count($nonViewableNodeIds) === 0
                    ? null
                    : new NotConstraint(new MetadataConstraint('node', $nonViewableNodeIds))
                )
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
}