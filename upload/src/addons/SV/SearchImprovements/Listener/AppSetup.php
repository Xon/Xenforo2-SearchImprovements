<?php

namespace SV\SearchImprovements\Listener;

abstract class AppSetup
{
    private function __construct() { }

    public static function appSetup(\XF\App $app): void
    {
        // Workaround for XF bug: https://xenforo.com/community/threads/all-redirects-to-search-results-should-include-search-query-arguments.212938/
        $linkBuilderRepo = $app->repository('SV\SearchImprovements:LinkBuilder');
        assert($linkBuilderRepo instanceof \SV\SearchImprovements\Repository\LinkBuilder);
        $linkBuilderRepo->hookSearchQueryBuilder();
    }
}