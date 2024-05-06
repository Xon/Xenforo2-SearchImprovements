<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Search;

class Thread extends XFCP_Thread
{
    /** @var class-string */
    protected static $svDiscussionEntity = \XF\Entity\Thread::class;
    /** @var bool */
    protected $svIndexPrefixes;
    use DiscussionTrait;

    public function __construct($contentType, Search $searcher)
    {
        parent::__construct($contentType, $searcher);
        $this->svIndexPrefixes = (bool)(\XF::options()->svPushThreadPrefixesIntoSearch ?? true);
    }

    public function getIndexData(Entity $entity)
    {
        /** @var \XF\Entity\Thread $entity */
        $index = parent::getIndexData($entity);

        if ($this->svIndexPrefixes && $index !== null)
        {
            $this->svIndexPrefixes($entity, $index);
        }

        return $index;
    }

    protected function svIndexPrefixes(\XF\Entity\Thread $thread, IndexRecord $index): void
    {
        $prefixIds = [];
        if (\XF::isAddOnActive('SV/MultiPrefix') && $thread->isValidColumn('sv_prefix_ids'))
        {
            /** @var \SV\MultiPrefix\XF\Entity\Thread $thread */
            $prefixIds = $thread->sv_prefix_ids;
        }
        else if ($thread->prefix_id)
        {
            $prefixIds = [$thread->prefix_id];
        }

        $language = \XF::app()->language();
        $prefixes = [];
        foreach ($prefixIds AS $prefixId)
        {
            $prefixId = (int)$prefixId;
            $key = 'thread_prefix.' . $prefixId;

            $phraseText = trim((string)$language->renderPhrase($key, [], 'raw', [
                'fallback' => null,
                'nameOnInvalid' => false,
            ]));
            if ($phraseText !== '')
            {
                $prefixes[$prefixId] = $phraseText;
            }
        }

        if (count($prefixes) !== 0)
        {
            $index->title .= ' ' . implode(' ', $prefixes);
        }
    }

    protected function getMetaData(\XF\Entity\Thread $entity)
    {
        $metaData = parent::getMetaData($entity);

        $this->populateDiscussionMetaData($entity, $metaData);

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
}