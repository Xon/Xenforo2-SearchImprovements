<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\SearchImprovements\Search;

use SV\SearchImprovements\EntityGetterCache;
use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\Features\ISearchableDiscussionUser;
use SV\SearchImprovements\Search\Features\ISearchableReplyCount;
use SV\SearchImprovements\Search\Features\SearchOrder;
use XF\Search\MetadataStructure;
use XF\Search\Query\SqlOrder;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function is_subclass_of;

/**
 * @property class-string $svDiscussionEntity
 */
trait DiscussionTrait
{
    /** @var class-string|null */
    protected $svDiscussionEntityClass = null;

    /**
     * @return class-string
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function getSvDiscussionEntityClass(): string
    {
        if ($this->svDiscussionEntityClass === null)
        {
            $this->svDiscussionEntityClass = static::$svDiscussionEntity ?? '';
            $this->svDiscussionEntityClass = \XF::extendClass($this->svDiscussionEntityClass);
        }

        return $this->svDiscussionEntityClass;
    }

    protected function populateDiscussionMetaData(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {
        $repo = Globals::repo();
        if (!$repo->isUsingElasticSearch())
        {
            return;
        }

        if ($repo->isPushingViewOtherChecksIntoSearch() && ($entity instanceof ISearchableDiscussionUser))
        {
            $this->setupDiscussionUserMetadata($entity, $metaData);

            $userIds= EntityGetterCache::getCachedValue($entity, '_svDiscussionUserIdForSearch', function () use ($entity): array {
                $userIds = $entity->getDiscussionUserIds();

                // ensure consistent behavior that it is an array of ints, and no zero user ids are sent to XFES
                /** @var int[] $userIds */
                $userIds = array_filter(array_map('\intval', $userIds), function (int $i) {
                    return $i !== 0;
                });
                // array_values ensures the value is encoded as a json array, and not a json hash if the php array is not a list
                /** @noinspection PhpUnnecessaryLocalVariableInspection */
                $userIds = array_values(array_unique($userIds));

                return $userIds;
            });
            assert(is_array($userIds));

            if (count($userIds) !== 0)
            {

                $metaData['discussion_user'] = $userIds;
            }
        }

        if ($entity instanceof ISearchableReplyCount)
        {
            $replyCount = EntityGetterCache::getCachedValue($entity, '_svGetReplyCountForSearch', function () use ($entity): int {
                return $entity->getReplyCountForSearch();
            });
            assert(is_int($replyCount));
            $metaData['replies'] = $replyCount;
        }
    }

    protected function setupDiscussionUserMetadata(\XF\Mvc\Entity\Entity $entity, array &$metaData): void
    {

    }

    public function setupDiscussionMetadataStructure(MetadataStructure $structure): void
    {
        $repo = Globals::repo();
        if (!$repo->isUsingElasticSearch())
        {
            return;
        }

        $class = $this->getSvDiscussionEntityClass();

        if (is_subclass_of($class, ISearchableDiscussionUser::class) && $repo->isPushingViewOtherChecksIntoSearch())
        {
            $structure->addField('discussion_user', MetadataStructure::INT);
            $this->setupDiscussionUserMetadataStructure($structure);
        }

        if (is_subclass_of($class, ISearchableReplyCount::class))
        {
            $structure->addField('replies', MetadataStructure::INT);
        }
    }

    /**
     * @param string|SqlOrder $order
     * @return SearchOrder|SqlOrder|string|null
     */
    public function getTypeOrder($order)
    {
        if ($order === 'replies' && Globals::repo()->isUsingElasticSearch())
        {
            $class = $this->getSvDiscussionEntityClass();
            if (is_subclass_of($class, ISearchableReplyCount::class))
            {
                return new SearchOrder(['replies', 'date']);
            }
        }

        return parent::getTypeOrder($order);
    }

    protected function setupDiscussionUserMetadataStructure(MetadataStructure $structure): void
    {
    }
}