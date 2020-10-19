<?php

namespace SV\SearchImprovements\XF\Search\Source;

\SV\StandardLib\Helper::repo()->aliasClass(
    'SV\SearchImprovements\XF\Search\Source\MySqlFt',
    \XF::$versionId < 2020000
        ? 'SV\SearchImprovements\XF\Search\Source\XF2\MySqlFt'
        : 'SV\SearchImprovements\XF\Search\Source\XF22\MySqlFt'
);
