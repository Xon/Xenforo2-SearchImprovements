<?php

namespace SV\SearchImprovements\Search;

use XF\Search\MetadataStructure;

class MetadataStructureEx extends MetadataStructure
{
    public function addKeyWordField(string $name): MetadataStructureField
    {
        return $this->addField($name, MetadataStructure::KEYWORD);
    }

    public function addStringField(string $name): MetadataStructureField
    {
        return $this->addField($name, MetadataStructure::STR);
    }

    public function addField($name, $type, array $config = []): MetadataStructureField
    {
        parent::addField($name, $type, $config);

        return new MetadataStructureField($this, $name);
    }

    /**
     * @param string $name
     * @return array|null
     */
    public function getField(string $name)
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * @param string $name
     * @param array  $config
     * @return void
     */
    public function updateField(string $name, array $config = [])
    {
        $this->fields[$name] = $config;
    }
}