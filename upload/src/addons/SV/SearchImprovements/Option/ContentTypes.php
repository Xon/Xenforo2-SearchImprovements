<?php

namespace SV\SearchImprovements\Option;

use XF\Option\AbstractOption;

/**
 * Class ContentTypes
 *
 * @package SV\SearchImprovements\Option
 */
class ContentTypes extends AbstractOption
{
    /**
     * @param \XF\Entity\Option $option
     * @param array             $htmlParams
     *
     * @return string
     */
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        $choices = [];

        $app = \XF::app();

        foreach ($app->getContentTypeField('search_handler_class') AS $contentType => $handlerClass)
        {
            if (class_exists($handlerClass))
            {
                $choices[] = [
                    'phraseName' => \XF::phrase($app->getContentTypePhraseName($contentType)),
                    'contentType' => $contentType,
                    'value' => isset($option->option_value[$contentType]) ? $option->option_value[$contentType] : null
                ];
            }
        }

        return self::getTemplate(
            'admin:svSearchImprov_option_template_content_type_weighting',
            $option,
            $htmlParams, [
                'choices' => $choices,
                'nextCounter' => \count($choices)
            ]
        );
    }

    /**
     * @param array $values
     *
     * @return bool
     */
    public static function verifyOption(array &$values)
    {
        $contentTypes = \XF::app()->getContentTypeField('search_handler_class');
        $output = [];

        foreach ($values AS $contentType => $value)
        {
            if (isset($contentTypes[$contentType]) && (bool)$value['checked'])
            {
                $output[$contentType] = (int) $value['value'];
            }
        }

        $values = $output;
        return true;
    }
}