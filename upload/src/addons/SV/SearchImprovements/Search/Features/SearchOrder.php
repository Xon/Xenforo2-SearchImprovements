<?php

namespace SV\SearchImprovements\Search\Features;

use function array_fill_keys;
use function array_key_exists;

class SearchOrder
{
    /** @var array<array<string,string>> */
    public $fields = [];

    public function __construct(array $fields)
    {
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