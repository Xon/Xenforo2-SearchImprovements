<?php

namespace SV\SearchImprovements\XF\Search\Query;

use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XF\Search\Query\TableReference;

/**
 * Class RangeMetadataConstraint
 *
 * @package SV\WordCountSearch\XF\Search\Query
 */
class RangeMetadataConstraint extends MetadataConstraint
{
    const MATCH_LESSER  = -42;
    const MATCH_GREATER = -41;
    const MATCH_BETWEEN = -40;

    /** @var TableReference[] */
    protected $tableReferences;
    /**  @var string */
    protected $source;

    /**
     * RangeMetadataConstraint constructor.
     *
     * @param string           $key
     * @param mixed            $values
     * @param int              $matchType
     * @param TableReference[] $tableReferences
     * @param string           $source
     */
    public function __construct($key, $values, $matchType, $tableReferences = [], $source = 'search_index')
    {
        parent::__construct($key, $values, $matchType);
        $this->tableReferences = $tableReferences;
        $this->source = $source;
    }

    /**
     * @param $match
     */
    public function setMatchType($match)
    {
        switch ($match)
        {
            case 'LESSER':
            case self::MATCH_LESSER:
                $this->matchType = self::MATCH_LESSER;
                break;

            case 'GREATER':
            case self::MATCH_GREATER:
                $this->matchType = self::MATCH_GREATER;
                break;

            case 'BETWEEN':
            case self::MATCH_BETWEEN:
                $this->matchType = self::MATCH_BETWEEN;
                break;

            default:
                parent::setMatchType($match);
                break;
        }
    }

    /**
     * @return null|SqlConstraint
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
                $sqlConstraint = new SqlConstraint("{$this->source}.{$this->key} >= %d and {$this->source}.{$this->key} <= %d ", $this->values);
                break;
        }

        if ($sqlConstraint && $this->tableReferences)
        {
            foreach ($this->tableReferences as $tableReference)
            {
                $sqlConstraint->addTable($tableReference);
            }
        }

        return $sqlConstraint;
    }
}