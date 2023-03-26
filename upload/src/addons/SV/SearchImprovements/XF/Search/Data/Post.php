<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\XF\Search\Query\Constraints\AndConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\ExistsConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use SV\StandardLib\Helper;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use function count;
use function is_array;

class Post extends XFCP_Post
{
    protected static $svDiscussionEntity = \XF\Entity\Thread::class;
    use DiscussionTrait;

    protected function getMetaData(\XF\Entity\Post $entity)
    {
        $metaData = parent::getMetaData($entity);

        $this->populateDiscussionMetaData($entity->Thread, $metaData);

        return $metaData;
    }

    protected function setupDiscussionUserMetadata(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {
        /** @var \XF\Entity\Thread $entity */
        if (\XF::isAddOnActive('SV/ViewStickyThreads'))
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
        if (\XF::isAddOnActive('SV/ViewStickyThreads'))
        {
            $structure->addField('sticky', MetadataStructure::BOOL);
        }
    }

    public function getTypePermissionConstraints(\XF\Search\Query\Query $query, $isOnlyType)
    {
        $constraints = parent::getTypePermissionConstraints($query, $isOnlyType) ?? [];
        // These are only meaningful with ElasticSearch+XenForo Enhanced Search
        $repo = Globals::repo();
        if (!$repo->isPushingViewOtherChecksIntoSearch())
        {
            return $constraints;
        }

        // Node permissions are flat data, but the visibility status encodes hierarchical view data
        $nodePerms = Helper::perms()->getPerContentPermissions('node');

        $nonViewableNodeIds = $viewableStickiesNodeIds = [];
        $viewStickies = \XF::isAddOnActive('SV/ViewStickyThreads');
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
            // Note; ElasticSearchEssentials forces all getTypePermissionConstraints to have $isOnlyType=true as it knows how to compose multiple types together
            $constraints[] = new OrConstraint(
                $isOnlyType ? null : new NotConstraint(new ExistsConstraint('node')),
                new MetadataConstraint('discussion_user', \XF::visitor()->user_id),
                count($viewableStickiesNodeIds) === 0
                    ? null
                    : new AndConstraint(
                    new ExistsConstraint('sticky'),
                    new MetadataConstraint('node', $viewableStickiesNodeIds)
                ),
                count($nonViewableNodeIds) === 0
                    ? null
                    : new NotConstraint(new MetadataConstraint('node', $nonViewableNodeIds))
            );
        }

        return $constraints;
    }
}