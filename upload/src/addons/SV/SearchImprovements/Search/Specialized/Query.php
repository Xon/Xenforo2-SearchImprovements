<?php

namespace SV\SearchImprovements\Search\Specialized;

use XF\Search\Query\SqlConstraint;

/**
 * @property $handler SpecializedData|\XF\Search\Data\AbstractData|null
 */
class Query extends \XF\Search\Query\Query
{
    /** @var string */
    protected $text = '';
    /** @var string[] */
    protected $textFields = [];
    /** @var bool */
    protected $withNgram = false;
    /** @var bool */
    protected $withExact = false;

    public function __construct(\XF\Search\Search $search, \XF\Search\Data\AbstractData $handler)
    {
        parent::__construct($search);

        $this->forTypeHandlerBasic($handler);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function textFields(): array
    {
        return $this->textFields;
    }

    /**
     * @param string $text
     * @param array  $fields
     * @return $this
     */
    public function matchQuery(string $text, array $fields): self
    {
        $this->text = $text;
        $this->textFields = $fields;

        return $this;
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

    public function withNgram(bool $withNgram = true): self
    {
        $this->withNgram = $withNgram;
        return $this;
    }

    public function isWithNgram(): bool
    {
        return $this->withNgram;
    }

    public function withExact(bool $withExact = true): self
    {
        $this->withExact = $withExact;
        return $this;
    }

    public function isWithExact(): bool
    {
        return $this->withExact;
    }
}