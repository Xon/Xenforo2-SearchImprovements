<?php

namespace SV\SearchImprovements\XFES\Admin\Controller;

use SV\SearchImprovements\Listener\LinkBuilder;
use SV\SearchImprovements\Repository\SpecializedSearchIndex as SpecializedSearchIndexRepo;
use SV\SearchImprovements\Search\Specialized\SpecializedData;
use SV\SearchImprovements\Service\Specialized\Configurer as SpecializedConfigurer;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\StandardLib\Helper;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Search\Data\AbstractData;
use XFES\Elasticsearch\Api as EsApi;
use XFES\Service\Analyzer;
use XFES\Service\Stats as StatsService;
use function strlen;

/**
 * Extends \XFES\Admin\Controller\EnhancedSearch
 */
class EnhancedSearch extends XFCP_EnhancedSearch
{
    /** @var string */
    protected $svShimContentType = '';

    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        /** @var \SV\SearchImprovements\XF\Search\SearchPatch $search */
        $search = \XF::app()->search();
        $search->specializedIndexProxying = false;

        $contentType = $params->get('content_type');
        if ($contentType)
        {
            $this->assertValidSpecializedContentType($contentType);
            $this->request()->set('content_type', $contentType);
        }

        $contentType = $this->filter('content_type', 'str');
        if ($contentType !== '')
        {
            $this->assertValidSpecializedContentType($contentType);
            $this->svShimContentType = $contentType;
            LinkBuilder::$contentType = $contentType;
        }
    }

    public function postDispatch($action, ParameterBag $params, Reply\AbstractReply &$reply)
    {
        if ($reply instanceof ViewReply)
        {
            $phrase = $this->svShimContentType !== ''
                ? $this->app()->getContentTypePhrase($this->svShimContentType , true)
                : \XF::phraseDeferred('svSearchImprovements_default_index');

            $reply->setParam('contentTypePhrase', $phrase);
            $reply->setParam('contentType', $this->svShimContentType);
        }

        parent::postDispatch($action, $params, $reply);
    }

    public function actionIndexes(ParameterBag $params): AbstractReply
    {
        $this->svShimContentType = '';
        $configurer = $this->getConfigurer();
        if (!$configurer->isEnabled() || !$configurer->hasActiveConfig())
        {
            return $this->redirect($this->buildLink('enhanced-search'));
        }
        /** @var \SV\SearchImprovements\XFES\Elasticsearch\Api $defaultEs */
        $defaultEs = $configurer->getEsApi();
        $esClusterStatus = [];
        $hasTestError = null;
        $version = null;
        $testError = null;
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
            catch (\XFES\Elasticsearch\Exception $e) {}
        }

        $contentType = (string)$params->get('content_type');
        if (strlen($contentType) !== 0)
        {
            return $this->actionSpecialized($params);
        }

        $this->setSectionContext('svSearchImprovements_xfes_indexes');

        $phrases = $this->app()->getContentTypePhrases(true, 'specialized_search_handler_class');

        $definitions = SpecializedSearchIndexRepo::get()->getSearchHandlerDefinitions();
        $definitions = ['' => ''] + $definitions;
        $indexes = [];
        foreach ($definitions as $contentType => $definition)
        {
            $this->svShimContentType = $contentType;
            $stats = null;
            $isOptimizable = false;
            $configurer = $this->getConfigurer();

            if ($configurer->hasActiveConfig())
            {
                $es = $configurer->getEsApi();

                try
                {
                    if ($contentType !== '')
                    {
                        $version = $es->version();
                        $hasTestError = $es->test($testError);
                    }

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
                catch (\XFES\Elasticsearch\Exception $e)
                {
                }
            }

            $indexes[$contentType] = [
                'phrase'        => $phrases[$contentType] ?? $contentType,
                'version'       => $version,
                'testError'     => $testError,
                'stats'         => $stats,
                'isOptimizable' => $isOptimizable,
            ];
        }
        $indexes['']['phrase'] = \XF::phraseDeferred('svSearchImprovements_default_index');
        $this->svShimContentType = '';

        $viewParams = [
            'es' => $defaultEs,
            'esClusterStatus' => $esClusterStatus,
            'testError' => $indexes['']['testError'] ?? '',
            'version' => $indexes['']['version'] ?? '',
            'indexes' => $indexes,
        ];

        return $this->view('', 'svSearchImprovements_xfes_indexes', $viewParams);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function actionSpecialized(ParameterBag $params): AbstractReply
    {
        return $this->actionIndex();
    }

    /**
     * @param array|null $config
     * @return \XFES\Service\Configurer|SpecializedConfigurer
     */
    protected function getConfigurer(?array $config = null)
    {
        if ($this->svShimContentType !== '')
        {
            return Helper::service(SpecializedConfigurer::class, $this->svShimContentType, $config);
        }

        return parent::getConfigurer($config);
    }

    /**
     * @param EsApi|null $es
     * @return \XFES\Service\Optimizer|SpecializedOptimizer
     */
    protected function getOptimizer(?EsApi $es = null)
    {
        if ($this->svShimContentType !== '')
        {
            $es = $es ?? SpecializedSearchIndexRepo::get()->getIndexApi($this->svShimContentType);

            return Helper::service(SpecializedOptimizer::class, $this->svShimContentType, $es);
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
            $es = $es ?? SpecializedSearchIndexRepo::get()->getIndexApi($this->svShimContentType);

            return Helper::service(SpecializedAnalyzer::class, $this->svShimContentType, $es);
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