<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Service\Specialized;

use SV\SearchImprovements\Repository\SpecializedSearchIndex as SpecializedSearchIndexRepo;
use SV\SearchImprovements\Search\Specialized\AbstractData;
use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\SearchImprovements\Service\Specialized\Configurer as SpecializedConfigurer;
use SV\SearchImprovements\XF\Search\SearchPatch;
use SV\StandardLib\Helper;
use XF\App;
use XFES\Elasticsearch\Api;
use XFES\Elasticsearch\Exception as ElasticSearchException;
use XFES\Elasticsearch\RequestException as ElasticSearchRequestException;
use function is_callable;

/**
 * @Extends \XFES\Service\Optimizer
 */
class Optimizer extends \XFES\Service\Optimizer
{
    /** @var string */
    protected $singleType;
    /** @var AbstractData|null */
    protected $searchHandler;
    /** @var bool  */
    protected    $ngramStripeWhiteSpace = true;
    /** @var bool */
    protected    $isSimpleTypeMapping = true;

    protected function ngramStripWhiteSpace(bool $value = true): self
    {
        $this->ngramStripeWhiteSpace = $value;
        return $this;
    }

    public static function get(string $singleType, ?Api $es = null, ?AbstractData $searchHandler = null): self
    {
        $es = $es ?? SpecializedSearchIndexRepo::get()->getIndexApi($singleType);

        return Helper::service(self::class, $singleType, $es, $searchHandler);
    }

    public function __construct(App $app, string $singleType, Api $es, ?AbstractData $searchHandler = null)
    {
        $this->singleType = $singleType;
        $this->searchHandler = $searchHandler;
        parent::__construct($app, $es);
    }

    public function optimize(array $settings = [], $updateConfig = false)
    {
        $configurer = SpecializedConfigurer::get($this->singleType, $this->es, $this->searchHandler);
        if (!$settings)
        {
            $analyzerConfig = $configurer->getAnalyzerConfig();
            $analyzer = SpecializedAnalyzer::get($this->singleType, $this->es, $this->searchHandler);
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
        if (!$this->isSimpleTypeMapping)
        {
            return parent::getBaseMapping();
        }

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

    protected function getTypeHandlerAndMapping(): array
    {
        /** @var SearchPatch $search */
        $search = \XF::app()->search();
        $typeHandler = $this->searchHandler;
        if ($typeHandler !== null && is_callable([$typeHandler, 'getSpecializedTypeFilter']))
        {
            $search->specializedTypeFilter = $typeHandler->getSpecializedTypeFilter();
        }
        else
        {
            $search->specializedTypeFilter = [$this->singleType => true];
            $typeHandler = \XF::app()->search()->getValidHandlers()[$this->singleType] ?? null;
        }
        $this->isSimpleTypeMapping = $typeHandler !== null ? $typeHandler->isSimpleTypeMapping() : true;
        try
        {
            $expectedMapping = parent::getExpectedMappingConfig();
        }
        finally
        {
            $search->specializedTypeFilter = null;
        }

        return [$typeHandler, $expectedMapping];
    }

    public function getExpectedMappingConfig(): array
    {
        /**
         * @var $typeHandler ?AbstractData
         * @var $expectedMapping array
         */
        [$typeHandler, $expectedMapping] = $this->getTypeHandlerAndMapping();

        if ($typeHandler !== null)
        {
            if (!$this->isSimpleTypeMapping)
            {
                return $expectedMapping;
            }
            $mdConfig = $typeHandler->getMetadataStructure();
        }
        else
        {
            $mdConfig = [];
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
        catch (ElasticSearchRequestException $e)
        {
            return true;
        }
        catch (ElasticSearchException $e)
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