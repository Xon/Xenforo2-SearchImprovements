<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Search\Specialized;

use XF\Search\Query\SqlConstraint;
use XF\Search\Search;
use function trim, strlen;

/**
 * @property $handler SpecializedData|\XF\Search\Data\AbstractData|null
 */
class Query extends \XF\Search\Query\Query
{
    /** @var array */
    protected $textMatches = [];
    /** @var bool  */
    protected $withPrefixPreferred = false;
    /** @var float|int */
    protected $prefixMatchBoost = 1.5;
    /** @var bool */
    protected $withNgram = false;
    /** @var bool */
    protected $withExact = false;
    /** @var string|float|int */
    protected $defaultFieldBoost = '^1.5';
    /** @var string|float|int */
    protected $exactBoost = '^2';
    /** @var string|float|int */
    protected $ngramBoost = '^1';
    /** @var string|float|int */
    protected $prefixDefaultFieldBoost = '^1';
    /** @var string|float|int */
    protected $prefixExactFieldBoost = '^1';
    /**  @var string */
    protected $fuzziness = '';
    //protected $fuzziness = 'AUTO:0,2';
    /** @var string */
    protected $matchQueryType = 'best_fields';//'most_fields'


    public function __construct(Search $search, \XF\Search\Data\AbstractData $handler)
    {
        parent::__construct($search);

        $this->orderedBy('_score');
        $this->forTypeHandlerBasic($handler);
        $this->types = [];
    }


    public function textMatches(): array
    {
        return $this->textMatches;
    }

    /**
     * Preform a text match to compute sort scoring
     *
     * @param string      $text
     * @param array       $fields
     * @param string|int|float|null $boost
     * @return $this
     */
    public function matchText(string $text, array $fields, $boost = null): self
    {
        $text = trim($text);
        if (strlen($text) !== 0)
        {
            if ($boost === null)
            {
                $boost = $this->defaultFieldBoost;
            }
            if (!is_string($boost))
            {
                $boost = '^'.$boost;
            }

            $this->textMatches[] = [$text, $fields, $boost];
        }

        return $this;
    }

    /**
     * Use matchText instead
     *
     * @deprecated
     */
    public function matchQuery(string $text, array $fields, ?string $boost = null): self
    {
        return $this->matchText($text, $fields, $boost);
    }

    public function withMatchQueryType(string $matchQueryType): self
    {
        $this->matchQueryType = $matchQueryType;

        return $this;
    }

    public function matchQueryType(): string
    {
        return $this->matchQueryType;
    }

    /**
     * @deprecated
     */
    public function inTypes(array $types): self
    {
        throw new \LogicException('Not supported');
    }

    /**
     * @deprecated
     */
    public function byUserIds(array $userIds): self
    {
        throw new \LogicException('Not supported');
    }

    /**
     * @deprecated
     */
    public function getGlobalUniqueQueryComponents(): array
    {
        throw new \LogicException('Not supported');
    }

    /**
     * @deprecated
     */
    public function withSql(SqlConstraint $constraint): self
    {
        throw new \LogicException('Not supported');
    }

    /**
     * @param bool           $withPrefixPreferred
     * @param float|int|null $prefixMatchBoost
     * @param float|int|string|null $prefixDefaultFieldBoost
     * @param float|int|string|null $prefixExactFieldBoost
     * @return static
     */
    public function withPrefixPreferred(bool $withPrefixPreferred = true, $prefixMatchBoost = null, $prefixDefaultFieldBoost = null, $prefixExactFieldBoost = null): self
    {
        $this->withPrefixPreferred = $withPrefixPreferred;
        if ($prefixMatchBoost !== null)
        {
            $this->prefixMatchBoost = $prefixMatchBoost;
        }
        if ($prefixDefaultFieldBoost !== null)
        {
            $this->prefixDefaultFieldBoost = is_string($prefixDefaultFieldBoost) ? $prefixDefaultFieldBoost : '^'.$prefixDefaultFieldBoost;
        }
        if ($prefixExactFieldBoost !== null)
        {
            $this->prefixExactFieldBoost = is_string($prefixExactFieldBoost) ? $prefixExactFieldBoost : '^'.$prefixExactFieldBoost;
        }
        return $this;
    }

    public function isWithPrefixPreferred(): bool
    {
        return $this->withPrefixPreferred;
    }

    /**
     * @return float|int|null
     */
    public function prefixMatchBoost()
    {
        return $this->prefixMatchBoost;
    }

    public function prefixDefaultFieldBoost(): string
    {
        return $this->prefixDefaultFieldBoost;
    }

    public function prefixExactFieldBoost(): string
    {
        return $this->prefixExactFieldBoost;
    }

    /**
     * @param bool        $withNgram
     * @param float|int|string|null $boost
     * @return static
     */
    public function withNgram(bool $withNgram = true, $boost = null): self
    {
        $this->withNgram = $withNgram;
        if ($boost !== null)
        {
            $this->ngramBoost = is_string($boost) ? $boost : '^'.$boost;
        }
        return $this;
    }

    public function isWithNgram(): bool
    {
        return $this->withNgram;
    }

    public function ngramBoost(): string
    {
        return $this->ngramBoost;
    }

    /**
     * @param bool                  $withExact
     * @param float|int|string|null $boost
     * @return static
     */
    public function withExact(bool $withExact = true, $boost = null): self
    {
        $this->withExact = $withExact;
        if ($boost !== null)
        {
            $this->exactBoost = is_string($boost) ? $boost : '^'.$boost;
        }
        return $this;
    }

    public function isWithExact(): bool
    {
        return $this->withExact;
    }

    public function exactBoost(): string
    {
        return $this->exactBoost;
    }

    /**
     * need to document this option can cause performance issues
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/common-options.html#fuzziness
     *
     * @param string $fuzziness
     * @return $this
     */
    public function withFuzzyMatching(string $fuzziness): self
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }

    public function fuzzyMatching(): string
    {
        return $this->fuzziness;
    }

    public function hasQueryConstraints(): bool
    {
        return false;
    }
}