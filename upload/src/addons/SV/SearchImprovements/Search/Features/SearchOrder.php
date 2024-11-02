<?php

namespace SV\SearchImprovements\Search\Features;

use XF\Search\Query\SqlOrder;
use function array_fill_keys;
use function array_key_exists;

class SearchOrder extends SqlOrder
{
    /** @var array<array<string,string>> */
    public $fields = [];

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
}