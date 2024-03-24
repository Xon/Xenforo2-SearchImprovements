<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XFES\Search\Source\Elasticsearch;
use function count;

class TypeConstraint extends AbstractConstraint
{
    public const MATCH_SV_TYPE = 'svTYPE';

    /**
     * @var array<string|null>
     */
    protected $contentTypes;

    public function __construct(?string ...$contentTypes)
    {
        parent::__construct('', $contentTypes, self::MATCH_SV_TYPE);
    }


    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_SV_TYPE => self::MATCH_SV_TYPE,
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
        /** @var string[] $types */
        $types = $this->getValues();
        if (count($types) === 0)
        {
            return;
        }

        /** @noinspection PhpDeprecationInspection */
        if ($source->es()->isSingleTypeIndex())
        {
            // types are now stored in a field in the index directly
            $source->svApplyMetadataConstraint(new MetadataConstraint('type', $types, MetadataConstraint::MATCH_ANY), $filters, $filtersNot);
        }
        else
        {
            $childFilters = [];
            foreach ($types as $type)
            {
                $childFilters[] = [
                    'type' => ['value' => $type]
                ];
            }
            $filters[] = [
                'bool' => [
                    'should' => $childFilters,
                    'minimum_should_match' => 1,
                ],
            ];
        }
    }
}