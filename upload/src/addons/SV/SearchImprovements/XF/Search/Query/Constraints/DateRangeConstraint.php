<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\SqlConstraint;
use XF\Search\Query\TableReference;
use XFES\Search\Source\Elasticsearch;
use function count;

class DateRangeConstraint extends RangeConstraint
{
    public const NEVER_IS_NA = 0;
    public const NEVER_IS_ZERO = 1; // for elasictic search NEVER_IS_ZERO gets mapped to NEVER_IS_NULL
    public const NEVER_IS_NULL = 2;
    /** @var int */
    protected $neverHandling;

    /**
     * RangeMetadataConstraint constructor.
     *
     * @param string           $key
     * @param mixed            $values
     * @param string|int       $matchType
     * @param int              $neverHandling
     * @param TableReference[] $tableReferences
     * @param string           $source
     */
    public function __construct(string $key, $values, $matchType, int $neverHandling, array $tableReferences = [], string $source = 'search_index')
    {
        parent::__construct($key, $values, $matchType, $tableReferences, $source);
        $this->setNeverHandling($neverHandling);
    }

    public function getNeverHandling(): int
    {
        return $this->neverHandling;
    }

    public function setNeverHandling(int $neverHandling): void
    {
        switch ($neverHandling)
        {
            case self::NEVER_IS_NA:
            case self::NEVER_IS_ZERO:
            case self::NEVER_IS_NULL:
                break;
            default:
                $this->throwUnknownNeverHandling($neverHandling);
        }
        $this->neverHandling = $neverHandling;
    }

    /**
     * @return never-return
     */
    protected function throwUnknownNeverHandling(?int $dbNeverHandling = null): void
    {
        throw new \LogicException('Unsupported never handling type:' . ($dbNeverHandling ?? $this->neverHandling));
    }

    /**
     * @return null|SqlConstraint|SqlConstraint[]
     */
    public function asSqlConstraint()
    {
        if ($this->neverHandling === self::NEVER_IS_NA)
        {
            return parent::asSqlConstraint();
        }

        $sqlConstraint = null;
        $key = "{$this->source}.{$this->key}";
        switch ($this->matchType)
        {
            case self::MATCH_LESSER:
                switch ($this->neverHandling)
                {
                    case self::NEVER_IS_ZERO:
                        $sqlConstraint = new SqlConstraint("{$key} <= %d AND {$key} > 0", $this->values);
                        break;
                    case self::NEVER_IS_NULL:
                        $sqlConstraint = new SqlConstraint("{$key} <= %d ", $this->values);
                        break;
                    default:
                        $this->throwUnknownNeverHandling();
                }
                break;
            case self::MATCH_GREATER:
                switch ($this->neverHandling)
                {
                    case self::NEVER_IS_ZERO:
                        $sqlConstraint = new SqlConstraint("{$key} >= %d ", $this->values);
                        break;
                    case self::NEVER_IS_NULL:
                        $sqlConstraint = new SqlConstraint("{$key} >= %d OR {$key} IS NULL", $this->values);
                        break;
                    default:
                        $this->throwUnknownNeverHandling();
                }
                break;
            case self::MATCH_BETWEEN:
                switch ($this->neverHandling)
                {
                    case self::NEVER_IS_ZERO:
                        $sqlConstraint = new SqlConstraint("({$key} >= %d AND {$key} > 0) AND {$key} <= %d ", $this->values);
                        break;
                    case self::NEVER_IS_NULL:
                        $sqlConstraint = new SqlConstraint("{$key} >= %d AND ({$key} <= %d OR {$key} IS NULL) ", $this->values);
                        break;
                    default:
                        $this->throwUnknownNeverHandling();
                }

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
     * @param array                                    $filters
     * @param array                                    $filtersNot
     */
    public function applyMetadataConstraint(Elasticsearch $source, array &$filters, array &$filtersNot)
    {
        if ($this->neverHandling === self::NEVER_IS_NA)
        {
            parent::applyMetadataConstraint($source, $filters, $filtersNot);

            return;
        }
        // for elasictic search NEVER_IS_ZERO gets mapped to NEVER_IS_NULL

        $key = $this->getKey();
        $values = $this->getValues();

        switch ($this->getMatchType())
        {
            case self::MATCH_GREATER:
                $filters[] = [
                    'bool' => [
                        'should' => [
                            [
                                'bool' => [
                                    'must_not' => [
                                        'exists' => [
                                            'field' => $key,
                                        ]
                                    ],
                                ]
                            ],
                            [
                                'range' => [
                                    $key => [
                                        'gte' => $values[0],
                                    ]
                                ]
                            ]
                        ],
                        'minimum_should_match' => 1
                    ],
                ];

                return;
            default:
                // Matching less than or between do not require special handling as a not-exist fields don't match anyway
                parent::applyMetadataConstraint($source, $filters, $filtersNot);
        }
    }
}