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
    /** @var ?float */
    protected $prefixMatchBoost = 2.5;
    /** @var bool */
    protected $withNgram = false;
    /** @var bool */
    protected $withExact = false;
    /** @var string */
    protected $defaultFieldBoost = '^1.5';
    /** @var string */
    protected $exactBoost = '^2';
    /** @var string */
    protected $ngramBoost = '';
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
     * @param string|null $boost
     * @return $this
     */
    public function matchText(string $text, array $fields, ?string $boost = null): self
    {
        $text = trim($text);
        if (strlen($text) !== 0)
        {
            $this->textMatches[] = [$text, $fields, $boost ?? $this->defaultFieldBoost];
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

    public function withPrefixPreferred(bool $withPrefixPreferred = true, ?float $prefixMatchBoost = null): self
    {
        $this->withPrefixPreferred = $withPrefixPreferred;
        if ($prefixMatchBoost !== null)
        {
            $this->prefixMatchBoost = $prefixMatchBoost;
        }
        return $this;
    }

    public function isWithPrefixPreferred(): bool
    {
        return $this->withPrefixPreferred;
    }

    public function prefixMatchBoost(): ?float
    {
        return $this->prefixMatchBoost;
    }

    public function withNgram(bool $withNgram = true, ?string $boost = null): self
    {
        $this->withNgram = $withNgram;
        if ($boost !== null)
        {
            $this->ngramBoost = $boost;
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

    public function withExact(bool $withExact = true, ?string $boost = null): self
    {
        $this->withExact = $withExact;
        if ($boost !== null)
        {
            $this->exactBoost = $boost;
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
}