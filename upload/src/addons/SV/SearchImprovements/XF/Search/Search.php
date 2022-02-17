<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search;

use XF\Search\Query;
use XF\Search\Query\KeywordQuery;
use XF\Search\Source\AbstractSource;

/**
 * Extends \XF\Search\Search
 */
class Search extends XFCP_Search
{
    protected $svAllowEmptySearch = false;

    public function __construct(AbstractSource $source, array $types)
    {
        parent::__construct($source, $types);
        $this->svAllowEmptySearch = !empty(\XF::options()->svAllowEmptySearch);
    }

    /**
     * @return bool
     */
    public function isSvAllowEmptySearch()
    {
        return $this->svAllowEmptySearch;
    }

    /**
     * @param bool $svAllowEmptySearch
     */
    public function setSvAllowEmptySearch($svAllowEmptySearch)
    {
        $this->svAllowEmptySearch = $svAllowEmptySearch;
    }

    /**
     * @param Query\Query $query
     * @param null        $error
     * @return bool
     */
    public function isQueryEmpty(Query\Query $query, &$error = null)
    {
        if ($this->svAllowEmptySearch && $query instanceof KeywordQuery)
        {
            $keywords = $query->getKeywords();
            if ($keywords === '*' || $keywords === '')
            {
                return false;
            }
        }

        return parent::isQueryEmpty($query, $error);
    }

    public function getParsedKeywords($keywords, &$error = null, &$warning = null)
    {
        if ($this->svAllowEmptySearch)
        {
            if ($keywords === '*' || $keywords === '')
            {
                return '';
            }
        }

        return parent::getParsedKeywords($keywords, $error, $warning);
    }
}