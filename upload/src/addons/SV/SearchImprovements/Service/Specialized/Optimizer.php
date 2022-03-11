<?php

namespace SV\SearchImprovements\Service\Specialized;

use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\SearchImprovements\Service\Specialized\Configurer as SpecializedConfigurer;

/**
 * Extends \XFES\Service\Optimizer
 */
class Optimizer extends \XFES\Service\Optimizer
{
    /** @var string */
    protected $singleType;
    /** @var bool  */
    protected $ngramStripeWhiteSpace = true;

    public function __construct(\XF\App $app, string $singleType, \XFES\Elasticsearch\Api $es)
    {
        $this->singleType = $singleType;
        parent::__construct($app, $es);
    }

    public function optimize(array $settings = [], $updateConfig = false)
    {
        /** @var SpecializedConfigurer $configurer */
        $configurer = $this->service(SpecializedConfigurer::class, $this->singleType, $this->es);
        if (!$settings)
        {
            $analyzerConfig = $configurer->getAnalyzerConfig();

            /** @var \XFES\Service\Configurer $xfConfigurer */
            $xfConfigurer = $this->service('XFES:Configurer', null);
            $xfAnalyzerConfig = $xfConfigurer->getAnalyzerConfig();

            /** @var SpecializedAnalyzer $analyzer */
            $analyzer = $this->service(SpecializedAnalyzer::class, $this->singleType, $this->es);
            foreach($analyzer->getDefaultConfig() as $key => $value)
            {
                $analyzerConfig[$key] = $xfAnalyzerConfig[$key] ?? $value;
            }
            $settings = $analyzer->getAnalyzerFromConfig($analyzerConfig);
        }

        $configurer->purgeIndex();
        // if we create an index in ES6+, we must force it to be single type
        $this->es->forceSingleType($this->es->majorVersion() >= 6);

        $config = [
            'settings' => $settings,
            'mappings' => $this->getExpectedMappingConfig(),
        ];

        $this->es->createIndex($config);
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
            $mapping['properties']['type'] = ['type' => 'keyword', 'skip-rewrite' => true];
        }

        return $mapping;
    }

    public function getExpectedMappingConfig()
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
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
            $keywordType = 'keyword';
        }
        else
        {
            $textType = 'string';
            $keywordType = 'string';
        }

        $apply = function (array &$properties) use ($textType, $keywordType) {
            foreach ($properties as &$mdColumn)
            {
                $skipRewrite = (bool)($mdColumn['skip-rewrite'] ?? false);
                unset($mdColumn['skip-rewrite']);
                if ($skipRewrite)
                {
                    continue;
                }

                if ($mdColumn['type'] === $keywordType || ($mdColumn['index'] ?? '') === 'not_analyzed')
                {
                    $mdColumn['type'] = $textType;
                    unset($mdColumn['index']);
                    $mdColumn['fields']['exact'] = [
                        'type' => $textType,
                        'analyzer' => 'sv_near_exact',
                    ];
                    $mdColumn['fields']['ngram'] = [
                        'type' => $textType,
                        'analyzer' => $this->ngramStripeWhiteSpace ? 'sv_keyword_ngram_no_whitespace' : 'sv_keyword_ngram',
                        'search_analyzer' => 'sv_near_exact',
                    ];
                }
                else if ($mdColumn['type'] === $textType)
                {
                    $mdColumn['fields']['exact'] = [
                        'type' => $textType,
                        'analyzer' => 'sv_near_exact',
                    ];
                    $mdColumn['fields']['ngram'] = [
                        'type' => $textType,
                        'analyzer' => 'sv_text_edge_ngram',
                        'search_analyzer' => 'sv_near_exact',
                    ];
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