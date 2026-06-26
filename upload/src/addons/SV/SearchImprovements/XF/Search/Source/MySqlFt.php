<?php
/**
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */

namespace SV\SearchImprovements\XF\Search\Source;

/** @noinspection PhpUndefinedClassInspection */
\SV\StandardLib\Helper::repo()->aliasClass(
    \SV\SearchImprovements\XF\Search\Source\MySqlFt::class,
    \XF::$versionId < 2020000
        ? \SV\SearchImprovements\XF\Search\Source\XF21\MySqlFt::class
        : \SV\SearchImprovements\XF\Search\Source\XF22\MySqlFt::class,
    \XF\Search\Source\MySqlFt::class
);
