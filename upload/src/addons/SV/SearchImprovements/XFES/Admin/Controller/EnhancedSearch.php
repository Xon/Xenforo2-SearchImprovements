<?php

namespace SV\SearchImprovements\XFES\Admin\Controller;

use SV\SearchImprovements\Listener\LinkBuilder;
use SV\SearchImprovements\Repository\SpecializedSearchIndex as SpecializedSearchIndexRepo;
use SV\SearchImprovements\Search\Specialized\SpecializedData;
use SV\SearchImprovements\Service\Specialized\Configurer as SpecializedConfigurer;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\SearchImprovements\XFES\Elasticsearch\Api as ExtendedApi;
use SV\StandardLib\Helper;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Search\Data\AbstractData;
use XFES\Elasticsearch\Api as EsApi;
use XFES\Elasticsearch\Exception as ElasticSearchException;
use XFES\Service\Analyzer;
use XFES\Service\Configurer as ConfigurerService;
use XFES\Service\Optimizer as OptimizerService;
use XFES\Service\Stats as StatsService;
use function strlen;

/**
 * @Extends \XFES\Admin\Controller\EnhancedSearch
 */
class EnhancedSearch extends XFCP_EnhancedSearch
{
    /** @var string */
    protected $svShimContentType = '';
    /** @var AbstractData|null */
    protected $svSearchHandler = null;

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        /** @var \SV\SearchImprovements\XF\Search\SearchPatch $search */
        $search = \XF::app()->search();
        $search->specializedIndexProxying = false;

        $contentType = (string)$params->get('content_type');
        if ($contentType !== '')
        {
            $this->svSearchHandler = $this->assertValidSpecializedContentType($contentType);
            $contentType = $this->svSearchHandler->getContentType();
            $this->request()->set('content_type', $contentType);
        }
        else
        {
            $contentType = $this->filter('content_type', 'str');
        }

        if ($contentType !== '')
        {
            $this->svSearchHandler = $this->assertValidSpecializedContentType($contentType);
            $contentType = $this->svSearchHandler->getContentType();
            $this->svShimContentType = $contentType;
            LinkBuilder::$contentType = $contentType;
        }
    }

    public function postDispatch($action, ParameterBag $params, Reply\AbstractReply &$reply)
    {
        if ($reply instanceof ViewReply)
        {
            $phrase = $this->svShimContentType !== ''
                ? \XF::app()->getContentTypePhrase($this->svShimContentType , true)
                : \XF::phraseDeferred('svSearchImprovements_default_index');

            $reply->setParam('contentTypePhrase', $phrase);
            $reply->setParam('contentType', $this->svShimContentType);
        }

        parent::postDispatch($action, $params, $reply);
    }

    public function actionIndexes(ParameterBag $params): AbstractReply
    {
        $this->svSearchHandler = null;
        $this->svShimContentType = '';
        $configurer = $this->getConfigurer();
        if (!$configurer->isEnabled() || !$configurer->hasActiveConfig())
        {
            return $this->redirect($this->buildLink('enhanced-search'));
        }
        $contentType = (string)$params->get('content_type');
        if (strlen($contentType) !== 0)
        {
            return $this->actionSpecialized($params);
        }

        $this->setSectionContext('svSearchImprovements_xfes_indexes');

        /** @var ExtendedApi $defaultEs */
        $defaultEs = $configurer->getEsApi();
        $esClusterStatus = [];
        $defaultIndex = $testError = null;
        if ($configurer->hasActiveConfig())
        {
            try
            {
                $version = $defaultEs->version();
                $hasTestError = $defaultEs->test($testError);

                if ($version && $hasTestError)
                {
                    $esClusterStatus = $defaultEs->getClusterInfo();
                }
            }
            catch (ElasticSearchException $e) {}

            $this->svSearchHandler = null;
            $this->svShimContentType = '';
            $defaultIndex = $this->analyzeIndex('', '');
            $defaultIndex['phrase'] = \XF::phraseDeferred('svSearchImprovements_default_index');
        }
        $indexes = [ '' => $defaultIndex];
        try
        {
            $indexes = $this->analyzeIndexes($indexes);
        }
        finally
        {
            $this->svSearchHandler = null;
            $this->svShimContentType = '';
        }

        $viewParams = [
            'es' => $defaultEs,
            'esClusterStatus' => $esClusterStatus,
            'testError' => $indexes['']['testError'] ?? '',
            'version' => $indexes['']['version'] ?? '',
            'indexes' => $indexes,
        ];

        return $this->view('', 'svSearchImprovements_xfes_indexes', $viewParams);
    }

    protected function analyzeIndexes(array $indexes): array
    {
        $phrases = \XF::app()->getContentTypePhrases(true, 'specialized_search_handler_class');

        $definitions = SpecializedSearchIndexRepo::get()->getSearchHandlerDefinitions();
        foreach ($definitions as $contentType => $definition)
        {
            $this->svSearchHandler = null;
            $this->svShimContentType = $contentType;
            $descriptor = $this->analyzeIndex($contentType, $definition);
            $id = $descriptor['id'];
            $descriptor['phrase'] = $descriptor['phrase'] ?? $phrases[$id] ?? $phrases[$contentType] ?? $contentType;
            $indexes[$id] = $descriptor;
        }

        return $indexes;
    }

    /**
     * @param string        $contentType
     * @param mixed $definition
     * @return array|null
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function analyzeIndex(string $contentType, $definition): ?array
    {
        $version = $testError = $stats = null;
        $isOptimizable = false;
        $configurer = $this->getConfigurer();

        if ($configurer->hasActiveConfig())
        {
            $es = $configurer->getEsApi();

            try
            {
                $version = $es->version();
                $hasTestError = $es->test($testError);

                if ($version && $hasTestError)
                {
                    if ($es->indexExists())
                    {
                        $isOptimizable = $this->getOptimizer($es)->isOptimizable();

                        /** @var StatsService $statsService */
                        $statsService = Helper::service(StatsService::class, $es);
                        $stats = $statsService->getStats();
                    }
                    else
                    {
                        $isOptimizable = true;
                    }
                }
            }
            catch (ElasticSearchException $e) {}
        }

        return [
            'id'            => $contentType,
            'version'       => $version,
            'testError'     => $testError,
            'stats'         => $stats,
            'isOptimizable' => $isOptimizable,
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function actionSpecialized(ParameterBag $params): AbstractReply
    {
        return $this->actionIndex();
    }

    /**
     * @param array|null $config
     * @return ConfigurerService|SpecializedConfigurer
     */
    protected function getConfigurer(?array $config = null)
    {
        if ($this->svShimContentType !== '')
        {
            return Helper::service(SpecializedConfigurer::class, $this->svShimContentType, $config, $this->svSearchHandler);
        }

        return parent::getConfigurer($config);
    }

    /**
     * @param EsApi|null $es
     * @return OptimizerService|SpecializedOptimizer
     */
    protected function getOptimizer(?EsApi $es = null)
    {
        if ($this->svShimContentType !== '')
        {
            return SpecializedOptimizer::get($this->svShimContentType, $es, $this->svSearchHandler);
        }

        return parent::getOptimizer($es);
    }

    /**
     * @param EsApi|null $es
     * @return Analyzer|SpecializedAnalyzer
     */
    protected function getAnalyzer(?EsApi $es = null)
    {
        if ($this->svShimContentType !== '')
        {
            return SpecializedAnalyzer::get($this->svShimContentType, $es, $this->svSearchHandler);
        }

        return parent::getAnalyzer($es);
    }

    /**
     * @param string $contentType
     * @return SpecializedData|AbstractData
     * @throws \Exception
     */
    protected function assertValidSpecializedContentType(string $contentType): SpecializedData
    {
        $handler = SpecializedSearchIndexRepo::get()->getHandler($contentType, false);
        if ($handler === null)
        {
            throw $this->exception($this->notFound());
        }

        LinkBuilder::$contentType = $contentType;

        return $handler;
    }
}