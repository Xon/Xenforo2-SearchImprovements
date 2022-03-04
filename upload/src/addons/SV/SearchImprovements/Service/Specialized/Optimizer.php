<?php

namespace SV\SearchImprovements\Service\Specialized;

/**
 * Extends \XFES\Service\Optimizer
 */
class Optimizer extends \XFES\Service\Optimizer
{
    /** @var string */
    protected $singleType;

    public function __construct(\XF\App $app, string $singleType, \XFES\Elasticsearch\Api $es)
    {
        $this->singleType = $singleType;
        parent::__construct($app, $es);
    }

    public function optimize(array $settings = [], $updateConfig = false)
    {
        /** @var Configurer $configurer */
        $configurer = $this->service('SV\SearchImprovements:Specialized\Configurer', $this->singleType, $this->es);
        if (!$settings)
        {
            $analyzerConfig = $configurer->getAnalyzerConfig();

            /** @var Analyzer $analyzer */
            $analyzer = $this->service('SV\SearchImprovements:Specialized\Analyzer', $this->singleType, $this->es);
            $settings = $analyzer->getAnalyzerFromConfig($analyzerConfig);
        }

        $configurer->purgeIndex();

        $config = [];

        if ($settings)
        {
            if (isset($config['settings']))
            {
                $config['settings'] = array_replace_recursive($config['settings'], $settings);
            }
            else
            {
                $config['settings'] = $settings;
            }
        }

        // if we create an index in ES6+, we must force it to be single type
        $this->es->forceSingleType($this->es->majorVersion() >= 6);

        $config['mappings'] = $this->getExpectedMappingConfig();
        $this->es->createIndex($config);


        parent::optimize($settings, false);
    }

    protected function getBaseMapping(): array
    {
        $version = $this->es->majorVersion();
        //$textType = ($version >= 5 ? 'text' : 'string');
        $mapping = [
            '_source'    => ['enabled' => false],
            '_all'       => ['enabled' => false],
            'properties' => [
                'hidden' => ['type' => 'boolean'],
            ],
        ];

        if ($version >= 6)
        {
            // this is disabled in 6+
            unset($mapping['_all']);
        }
        // todo remove
        if ($this->es->isSingleTypeIndex())
        {
            $mapping['properties']['type'] = ['type' => 'keyword'];
        }

        return $mapping;
    }

    public function getExpectedMappingConfig()
    {
        $this->app->search()->specializedTypeFilter = $this->singleType;
        try
        {
            $expectedMapping = parent::getExpectedMappingConfig();
        }
        finally
        {
            $this->app->search()->specializedTypeFilter = null;
        }


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
            'analyzer' => 'sv_ngram_analyzer',
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