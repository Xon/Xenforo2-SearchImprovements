<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Search\Specialized;

use SV\SearchImprovements\Search\MetadataStructureEx;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;

/**
 * @template T of Entity
 * @extends \XF\Search\Data\AbstractData<T>
 */
abstract class AbstractData extends \XF\Search\Data\AbstractData implements SpecializedData
{
    public function getMetadataStructure(): array
    {
        $structure = new MetadataStructureEx();
        $this->setupMetadataStructureEx($structure);

        return $structure->getFields();
    }

    public function isSimpleTypeMapping(): bool
    {
        return true;
    }

    public function canReassignContent(): bool
    {
        return false;
    }

    abstract public function setupMetadataStructureEx(MetadataStructureEx $structure);

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure = new MetadataStructureEx();
        $this->setupMetadataStructureEx($structure);

        foreach ($structure->getFields() as $name => $config)
        {
            $structure->addField($name, $config['type'], $config);
        }
    }
}