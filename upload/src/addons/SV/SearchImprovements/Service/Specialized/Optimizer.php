<?php

namespace SV\SearchImprovements\Service\Specialized;

use SV\ElasticSearchEssentials\XFES\Service\Configurer;

/**
 * Extends \XFES\Service\Optimizer
 */
class Optimizer extends \XFES\Service\Optimizer
{
    public function optimize(array $settings = [], $updateConfig = false)
    {
        /** @var Configurer $configurer */
        $configurer = $this->service('XFES:Configurer', $this->es);
        if (!$settings)
        {
            $analyzerConfig = $configurer->getAnalyzerConfig();

            /** @var \XFES\Service\Analyzer $analyzer */
            $analyzer = $this->service('XFES:Analyzer', $this->es);
            $settings = $analyzer->getAnalyzerFromConfig($analyzerConfig);
        }

        $configurer->purgeIndex();

        parent::optimize($settings, $updateConfig);
    }

    public function getExpectedMappingConfig()
    {
        $expectedMapping = parent::getExpectedMappingConfig();

        if ($this->es->majorVersion() >= 5)
        {
            $textType = 'text';
        }
        else
        {
            $textType = 'string';
        }

        $exactTextType = [
            'type'     => $textType,
            'analyzer' => 'sv_near_exact_analyzer',
        ];
        $ngramTextType = [
            'type'     => $textType,
            'analyzer' => 'sv_ngram_filter',
        ];

        $apply = function (array &$properties) use ($textType, $exactTextType, $ngramTextType) {
            foreach ($properties as &$mdColumn)
            {
                if ($mdColumn['type'] === $textType && !isset($mdColumn['index']))
                {
                    $mdColumn['fields']['exact'] = $exactTextType;
                    $mdColumn['fields']['ngram'] = $ngramTextType;
                }
            }
        };

        // ElasticSearch v7+ can support typeless results
        if (isset($expectedMapping['properties']))
        {
            $apply($expectedMapping['properties']);
        }
        else
        {
            foreach ($expectedMapping as &$mapping)
            {
                $apply($mapping['properties']);
            }
        }

        return $expectedMapping;
    }
}