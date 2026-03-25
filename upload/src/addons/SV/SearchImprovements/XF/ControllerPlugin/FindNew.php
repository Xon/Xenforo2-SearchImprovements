<?php

namespace SV\SearchImprovements\XF\ControllerPlugin;

use SV\SearchImprovements\Repository\Search as svSearchRepo;
use XF\FindNew\AbstractHandler;

/**
 * @extends \XF\ControllerPlugin\FindNew
 */
class FindNew extends XFCP_FindNew
{
    public function runFindNewSearch(AbstractHandler $handler, array $filters)
    {
        $options = \XF::options();
        $oldLimit = $options->maximumSearchResults ?? 200;
        $options->maximumSearchResults = svSearchRepo::get()->getSearchLimit();
        try
        {
            return parent::runFindNewSearch($handler, $filters);
        }
        finally
        {
            $options->maximumSearchResults = $oldLimit;
        }
    }
}