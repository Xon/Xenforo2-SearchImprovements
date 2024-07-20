<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Service\Specialized;

use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\SearchImprovements\Service\Specialized\Configurer as SpecializedConfigurer;
use SV\StandardLib\Helper;

/**
 * @Extends \XFES\Service\Optimizer
 */
class Optimizer extends \XFES\Service\Optimizer
{
    /** @var string */
    protected $singleType;
    /** @var bool  */
    protected $ngramStripeWhiteSpace = true;

    protected function ngramStripWhiteSpace(bool $value = true): self
    {
        $this->ngramStripeWhiteSpace = $value;
        return $this;
    }

    public function __construct(\XF\App $app, string $singleType, \XFES\Elasticsearch\Api $es)
    {
        $this->singleType = $singleType;
        parent::__construct($app, $es);
    }

    public function optimize(array $settings = [], $updateConfig = false)
    {
        $configurer = Helper::service(SpecializedConfigurer::class, $this->singleType, $this->es);
        if (!$settings)
        {
            $analyzerConfig = $configurer->getAnalyzerConfig();
            $analyzer = Helper::service(SpecializedAnalyzer::class, $this->singleType, $this->es);
            // seed config from the main index
            if (!$this->es->indexExists())
            {
                $xfConfigurer = Helper::service(\XFES\Service\Configurer ::class, null);
                $xfAnalyzerConfig = $xfConfigurer->getAnalyzerConfig();

                $this->seedFromMainIndex($analyzer, $xfConfigurer, $xfAnalyzerConfig, $analyzerConfig);
            }
            $settings = $analyzer->getAnalyzerFromConfig($analyzerConfig);
        }

        $configurer->purgeIndex();
        // if we create an index in ES6+, we must force it to be single type
        /** @noinspection PhpDeprecationInspection */
        $this->es->forceSingleType($this->es->majorVersion() >= 6);

        $config = [
            'settings' => $settings,
            'mappings' => $this->getExpectedMappingConfig(),
        ];

        $this->es->createIndex($config);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function seedFromMainIndex(SpecializedAnalyzer $analyzer, \XFES\Service\Configurer $xfConfigurer, array $xfAnalyzerConfig, array &$analyzerConfig)
    {
        foreach ($analyzer->getDefaultConfig() as $key => $value)
        {
            $analyzerConfig[$key] = $xfAnalyzerConfig[$key] ?? $value;
        }

        // force the minimum ngram size to a better default
        $ngramConfig = $analyzer->getNgramFilter([]);
        if (isset($analyzerConfig['sv_ess_ngram']['min_gram']) && $analyzerConfig['sv_ess_ngram']['min_gram'] < $ngramConfig['min_gram'])
        {
            $analyzerConfig['sv_ess_ngram']['min_gram'] = $ngramConfig['min_gram'];
        }
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
        /** @noinspection PhpDeprecationInspection */
        if ($this->es->isSingleTypeIndex())
        {
            $mapping['properties']['type'] = ['type' => 'keyword', 'skip-rewrite' => true];
        }

        return $mapping;
    }

    public function getExpectedMappingConfig(): array
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $this->app->search()->specializedTypeFilter = $this->singleType;
        try
        {
            $typeHandler = $this->app->search()->getValidHandlers()[$this->singleType] ?? null;
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

        $mdConfig = $typeHandler !== null ? $typeHandler->getMetadataStructure() : [];

        $apply = function (array &$properties) use ($textType, $keywordType, $mdConfig) {
            foreach ($properties as $key => &$mdColumn)
            {
                $skipRewrite = (bool)($mdColumn['skip-rewrite'] ?? false);
                unset($mdColumn['skip-rewrite']);
                if ($skipRewrite)
                {
                    continue;
                }

                $fullConfig = $mdConfig[$key] ?? [];
                $skipRewrite = (bool)($fullConfig['skip-rewrite'] ?? false);
                if ($skipRewrite)
                {
                    continue;
                }
                $stripeWhitespace = (bool)($fullConfig['stripe-whitespace-from-exact'] ?? false);

                if ($mdColumn['type'] === $keywordType || ($mdColumn['index'] ?? '') === 'not_analyzed')
                {
                    $mdColumn['type'] = $textType;
                    unset($mdColumn['index']);
                    $mdColumn['fields']['exact'] = [
                        'type' => $textType,
                        'analyzer' => $stripeWhitespace ? 'sv_near_exact_no_whitespace' : 'sv_near_exact',
                    ];
                    $mdColumn['fields']['ngram'] = [
                        'type' => $textType,
                        'analyzer' => $this->ngramStripeWhiteSpace ? 'sv_keyword_ngram_no_whitespace' : 'sv_keyword_ngram',
                        'search_analyzer' => $stripeWhitespace ? 'sv_near_exact_no_whitespace' : 'sv_near_exact',
                    ];
                }
                else if ($mdColumn['type'] === $textType)
                {
                    $mdColumn['fields']['exact'] = [
                        'type' => $textType,
                        'analyzer' => $stripeWhitespace ? 'sv_near_exact_no_whitespace' : 'sv_near_exact',
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

    public function getLiveMappingConfig()
    {
        try
        {
            $config = $this->es->getIndexInfo();
        }
        catch (\XFES\Elasticsearch\RequestException $e)
        {
            return true;
        }
        catch (\XFES\Elasticsearch\Exception $e)
        {
            return false;
        }

        if (!$config || empty($config['mappings']))
        {
            return true;
        }

        $liveMappings = $config['mappings'];
        $mappingType = key($liveMappings);
        if ($mappingType === '_doc')
        {
            // ES7 may not have an explicit type, so we may _doc as the type, whereas we're expecting "xf".
            // When this happens, we won't be passing types into URLs, so we should be able to ignore it.
            /** @noinspection PhpDeprecationInspection */
            $liveMappings = [$this->es->getSingleTypeName() => $liveMappings['_doc']];
        }

        return $liveMappings;
    }
}