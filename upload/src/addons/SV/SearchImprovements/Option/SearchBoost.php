<?php

namespace SV\SearchImprovements\Option;

use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;

abstract class SearchBoost extends AbstractOption
{
    public const DEFAULT = [
        'default' => 1,
        'exact' => 3,
        'ngram' => 0.5,
        'prefix' => 1.5,
        'prefix_default' => 1,
        'prefix_exact' => 3,
    ];

    /**
     * @return array{default:float|int, exact:float|int, ngram:float|int, prefix:float|int, prefix_default:float|int, prefix_exact:float|int}
     */
    public static function get($boosts): array
    {
        if (is_array($boosts))
        {
            foreach (self::DEFAULT as $k => $v)
            {
                if (!array_key_exists($k, $boosts))
                {
                    $boosts[$k] = $v;
                }
            }
            return $boosts;
        }

        return [];
    }

    public static function renderOption(OptionEntity $option, array $htmlParams): string
    {
        $choices = [];

        $optionValue = self::get($option->option_value);

        foreach (self::DEFAULT as $key => $default)
        {
            $default = $default ?? 1;
            $value = $optionValue[$key] ?? $default;
            $choices[] = [
                'phraseName' => \XF::phrase('svSearchImprov_search_boost_key.' . $key),
                'key'        => $key,
                'value'      => $value === $default ? $default : $value,
                'selected'   => $value !== $default,
                'default'    => $default,
            ];
        }

        return self::getTemplate('admin:svSearchImprov_option_template_search_boosts', $option, $htmlParams, [
            'choices'     => $choices,
            'nextCounter' => count($choices)
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function verifyOption(&$values, OptionEntity $option): bool
    {
        $output = [];

        foreach (self::DEFAULT as $k => $v)
        {
            $value = $values[$k] ?? ['checked' => false];

            if ($value['checked'] ?? false)
            {
                /** @noinspection PhpWrongStringConcatenationInspection */
                $v = strval($value['value']) + 0;
                if ($v < 0)
                {
                    $v = 0;
                }
            }

            if (self::DEFAULT[$k] !== $v)
            {
                $output[$k] = $v;
            }
        }

        $values = $output;

        return true;
    }
}