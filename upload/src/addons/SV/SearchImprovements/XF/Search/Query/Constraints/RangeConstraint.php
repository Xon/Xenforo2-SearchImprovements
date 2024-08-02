<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\SqlConstraint;
use XF\Search\Query\TableReference;
use XFES\Search\Source\Elasticsearch;
use function count;

class RangeConstraint extends AbstractConstraint
{
    public const MATCH_LESSER = -42; // $a <= foo
    public const MATCH_GREATER = -41; // $b >= foo
    public const MATCH_BETWEEN = -40; // $b <= foo <= $a

    /** @var TableReference[] */
    protected $tableReferences;
    /**  @var string */
    protected $source;

    /**
     * RangeMetadataConstraint constructor.
     *
     * @param string           $key
     * @param mixed            $values
     * @param string|int       $matchType
     * @param TableReference[] $tableReferences
     * @param string           $source
     */
    public function __construct(string $key, $values, $matchType, array $tableReferences = [], string $source = 'search_index')
    {
        parent::__construct($key, $values, $matchType);
        $this->tableReferences = $tableReferences;
        $this->source = $source;
    }

    /**
     * @param array{0:int|string,0:int|string}|array{0:int|string}|int|string $values
     * @return void
     */
    public function setValues($values)
    {
        if (!is_array($values))
        {
            $values = [$values];
        }
        if (count($values) === 0 || count($values) > 2)
        {
            throw new \LogicException(self::class . '::setValues() expects either $lower/$upper/[$lower,$upper] arguments');
        }

        parent::setValues($values);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            'LESSER' => self::MATCH_LESSER,
            self::MATCH_LESSER => self::MATCH_LESSER,

            'GREATER' => self::MATCH_GREATER,
            self::MATCH_GREATER => self::MATCH_GREATER,

            'BETWEEN' => self::MATCH_BETWEEN,
            self::MATCH_BETWEEN => self::MATCH_BETWEEN,
        ];
    }

    /**
     * @return null|SqlConstraint|SqlConstraint[]
     */
    public function asSqlConstraint()
    {
        $sqlConstraint = null;
        switch ($this->matchType)
        {
            case self::MATCH_LESSER:
                $sqlConstraint = new SqlConstraint("{$this->source}.{$this->key} <= %d ", $this->values);
                break;
            case self::MATCH_GREATER:
                $sqlConstraint = new SqlConstraint("{$this->source}.{$this->key} >= %d ", $this->values);
                break;
            case self::MATCH_BETWEEN:
                $sqlConstraint = new SqlConstraint("{$this->source}.{$this->key} <= %d AND {$this->source}.{$this->key} >= %d ", $this->values);
                break;
        }

        if ($sqlConstraint !== null && count($this->tableReferences) !== 0)
        {
            foreach ($this->tableReferences as $tableReference)
            {
                $sqlConstraint->addTable($tableReference);
            }
        }

        return $sqlConstraint;
    }

    /**
     * @param Elasticsearch|MetadataSearchEnhancements $source
     * @param array         $filters
     * @param array         $filtersNot
     */
    public function applyMetadataConstraint(Elasticsearch $source, array &$filters, array &$filtersNot)
    {
        $key = $this->getKey();
        $values = $this->getValues();

        switch ($this->getMatchType())
        {
            case self::MATCH_LESSER:
                $filters[] = [
                    'range' => [
                        $key => [
                            'lte' => $values[0],
                        ]
                    ]
                ];

                return;
            case self::MATCH_GREATER:
                $filters[] = [
                    'range' => [
                        $key => [
                            'gte' => $values[0],
                        ]
                    ]
                ];

                return;
            case self::MATCH_BETWEEN:
                $filters[] = [
                    'range' => [
                        $key => [
                            'lte' => $values[0],
                            'gte' => $values[1],
                        ]
                    ]
                ];

                return;
        }
    }
}