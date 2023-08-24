<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XFES\Search\Source\Elasticsearch;

class ExistsConstraint extends AbstractConstraint
{
    public const MATCH_SV_EXISTS = 'svEXISTS';

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(string $key)
    {
        $this->key = $key;
        $this->values = [];
        $this->setMatchType(self::MATCH_SV_EXISTS);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_SV_EXISTS => self::MATCH_SV_EXISTS,
        ];
    }

    public function asSqlConstraint()
    {
        // TODO: Implement asSqlConstraint() method.
        return null;
    }

    /**
     * @param Elasticsearch|MetadataSearchEnhancements $source
     * @param array         $filters
     * @param array         $filtersNot
     */
    public function applyMetadataConstraint(Elasticsearch $source, array &$filters, array &$filtersNot)
    {
        $filters[] = [
            'exists' => ['field' => $this->key],
        ];
    }
}