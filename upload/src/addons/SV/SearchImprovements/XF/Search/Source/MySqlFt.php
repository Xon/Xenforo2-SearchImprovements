<?php

namespace SV\SearchImprovements\XF\Search\Source;

if (\XF::$versionId < 2020000)
{
    \class_alias('SV\SearchImprovements\XF\Search\Source\XF2\MySqlFt', 'SV\SearchImprovements\XF\Search\Source\MySqlFt');
}
else
{
    \class_alias('SV\SearchImprovements\XF\Search\Source\XF22\MySqlFt', 'SV\SearchImprovements\XF\Search\Source\MySqlFt');
}