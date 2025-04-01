<?php

namespace SV\SearchImprovements\Search\Features;

use XF\Search\Query\Query;
use XF\Search\Query\SqlOrder;
use XFES\Elasticsearch\Api;
use Closure;
use function array_fill_keys;
use function array_key_exists;
use function count;
use function in_array;

class SearchOrder extends SqlOrder
{
    /** @var array<array<string,string>> */
    public $fields = [];
    /**  @var array<array,Closure(Query,Api):array> */
    protected $functions = [];

    public function __construct(array $fields)
    {
        parent::__construct('custom', null);

        if (array_key_exists(0, $fields))
        {
            $fields = array_fill_keys($fields, 'desc');
        }

        foreach ($fields as $key => $value)
        {
            $this->fields[] = [$key => $value];
        }
    }

    /**
     * @param array|Closure(Query,Api):array $function
     * @return static
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function addFunction($function)
    {
        $this->functions[] = $function;

        return $this;
    }

    /**
     * @return array<array,Closure(Query,Api):array>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function xfesOnly(): bool
    {
        return count($this->functions) !== 0 || in_array('_score', $this->fields, true);
    }
}