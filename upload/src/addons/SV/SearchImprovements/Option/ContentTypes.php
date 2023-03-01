<?php

namespace SV\SearchImprovements\Option;

use XF\Option\AbstractOption;

use function count, floatval;

/**
 * Class ContentTypes
 *
 * @package SV\SearchImprovements\Option
 */
class ContentTypes extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams): string
    {
        $choices = [];

        $app = \XF::app();
        $search = $app->search();

        foreach ($app->getContentTypeField('search_handler_class') as $contentType => $handlerClass)
        {
            if ($search->isValidContentType($contentType) && \class_exists($handlerClass))
            {
                $choices[] = [
                    'phraseName'  => \XF::phrase($app->getContentTypePhraseName($contentType)),
                    'contentType' => $contentType,
                    'value'       => $option->option_value[$contentType] ?? 1,
                    'selected'    => isset($option->option_value[$contentType]),
                ];
            }
        }

        return self::getTemplate(
            'admin:svSearchImprov_option_template_content_type_weighting',
            $option,
            $htmlParams, [
                'choices'     => $choices,
                'nextCounter' => count($choices)
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
                /** @noinspection PhpIdempotentOperationInspection */
                $value = floatval($value['value']) + 0;
                if ($value == 1 || $value < 0)
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