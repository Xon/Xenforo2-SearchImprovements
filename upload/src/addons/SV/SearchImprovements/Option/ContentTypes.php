<?php

namespace SV\SearchImprovements\Option;

use SV\SearchImprovements\Util\IndexHelper;
use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;
use function class_exists;
use function count;
use function floatval;

abstract class ContentTypes extends AbstractOption
{
    public static function renderOption(OptionEntity $option, array $htmlParams): string
    {
        $choices = [];

        $app = \XF::app();
        $search = $app->search();
        $optionValue = $option->option_value;
        $contentTypes = \XF::app()->getContentTypeField('search_handler_class');

        foreach ($contentTypes as $contentType => $handlerClass)
        {
            if ($search->isValidContentType($contentType) && class_exists($handlerClass))
            {
                $value = $optionValue[$contentType] ?? null;
                $choices[] = [
                    'phraseName'  => \XF::phrase($app->getContentTypePhraseName($contentType)),
                    'contentType' => $contentType,
                    'value'       => $value ?? 1,
                    'selected'    => $value !== null,
                ];
            }
        }

        return self::getTemplate(
            'admin:svSearchImprov_option_template_content_type_weighting',
            $option,
            $htmlParams, [
                'choices'     => $choices,
                'nextCounter' => count($choices),
            ]
        );
    }

    public static function verifyOption(array &$values): bool
    {
        $contentTypes = \XF::app()->getContentTypeField('search_handler_class');
        $output = [];

        foreach ($values as $contentType => $value)
        {
            if (isset($contentTypes[$contentType]) && ($value['checked'] ?? false))
            {
                $value = IndexHelper::asIntOrFloat($value['value']);
                if ($value === 1 || $value < 0)
                {
                    continue;
                }
                $output[$contentType] = $value;
            }
        }

        $values = $output;

        return true;
    }
}