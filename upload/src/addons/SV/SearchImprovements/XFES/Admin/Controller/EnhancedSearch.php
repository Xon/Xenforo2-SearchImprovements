<?php

namespace SV\SearchImprovements\XFES\Admin\Controller;

use SV\SearchImprovements\Repository\SpecializedSearchIndex;
use SV\SearchImprovements\Search\Specialized\SpecializedData;
use SV\SearchImprovements\Service\Specialized\Configurer as SpecializedConfigurer;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect as RedirectReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Search\Data\AbstractData;
use XFES\Service\Stats as StatsService;

/**
 * Extends \XFES\Admin\Controller\EnhancedSearch
 */
class EnhancedSearch extends XFCP_EnhancedSearch
{
    public function preDispatch($action, ParameterBag $params)
    {
        /** @var \SV\SearchImprovements\XF\Search\SearchPatch $search */
        $search = \XF::app()->search();
        $search->specializedIndexProxying = false;
        parent::preDispatch($action, $params);
    }

    /** @var string */
    protected $svShimContentType = '';

    public function actionIndexes(ParameterBag $params): AbstractReply
    {
        $contentType = (string)$params->get('content_type');
        if (\strlen($contentType) !== 0)
        {
            return $this->actionSpecializedView($params);
        }

        $this->setSectionContext('svSearchImprovements_xfes_indexes');

        $phrases = $this->app()->getContentTypePhrases(true, 'specialized_search_handler_class');

        $repo = $this->getSpecializedSearchIndexRepo();
        $definitions = $repo->getSearchHandlerDefinitions();
        $definitions = ['' => ''] + $definitions;
        $indexes = [];
        $version = null;
        $testError = null;
        $hasTestError = null;
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
                    $version = $version ?? $es->version();
                    $hasTestError = $hasTestError ?? $es->test($testError);

                    if ($version && $hasTestError)
                    {
                        if ($es->indexExists())
                        {
                            $isOptimizable = $this->getOptimizer($es)->isOptimizable();

                            /** @var StatsService $statsService */
                            $statsService = $this->service(StatsService::class, $es);
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
                'testError'     => $testError,
                'stats'         => $stats,
                'isOptimizable' => $isOptimizable,
            ];
        }
        $indexes['']['phrase'] = \XF::phraseDeferred('svSearchImprovements_default_index');
        $this->svShimContentType = '';

        $viewParams = [
            'version' => $version,
            'indexes' => $indexes,
        ];

        return $this->view('', 'svSearchImprovements_xfes_indexes', $viewParams);
    }

    public function actionOptimize()
    {
        $contentType = (string)$this->filter('content_type', 'str');
        if ($contentType !== '')
        {
            $repo = $this->getSpecializedSearchIndexRepo();
            $handler = $repo->getHandler($contentType, false);
            if ($handler === null)
            {
                throw $this->exception($this->notFound());
            }
        }

        $this->svShimContentType = $contentType;
        try
        {
            $reply = parent::actionOptimize();

            if ($reply instanceof ViewReply)
            {
                $phrase = $this->svShimContentType !== ''
                    ? $this->app()->getContentTypePhrase($this->svShimContentType , true)
                    : \XF::phraseDeferred('svSearchImprovements_default_index');

                $reply->setParam('contentTypePhrase', $phrase);
                $reply->setParam('contentType', $this->svShimContentType);
            }
            else if ($reply instanceof RedirectReply &&
                     $this->svShimContentType !== '' &&
                     $reply->getUrl() === $this->buildLink('enhanced-search', null, ['reindex' => 1]))
            {
                $reply->setUrl($this->buildLink('enhanced-search/specialized', ['content_type' => $this->svShimContentType], ['reindex' => 1]));
            }

            return $reply;
        }
        finally
        {
            $this->svShimContentType = '';
        }
    }

    public function actionSpecialized(ParameterBag $params): AbstractReply
    {
        $handler = $this->assertValidSpecializedContentType($params->get('content_type'));
        $this->svShimContentType = $handler->getContentType();
        try
        {
            $reply = $this->actionIndex();
            if ($reply instanceof ViewReply)
            {
                $phrase = $this->svShimContentType !== ''
                    ? $this->app()->getContentTypePhrase($this->svShimContentType , true)
                    : \XF::phraseDeferred('svSearchImprovements_default_index');

                $reply->setParam('contentTypePhrase', $phrase);
                $reply->setParam('contentType', $this->svShimContentType);
            }

            return $reply;
        }
        finally
        {
            $this->svShimContentType = '';
        }
    }

    /**
     * @param array|null $config
     * @return \XFES\Service\Configurer|SpecializedConfigurer
     */
    protected function getConfigurer(array $config = null)
    {
        if ($this->svShimContentType !== '')
        {
            /** @var SpecializedConfigurer $service */
            /** @noinspection PhpUnnecessaryLocalVariableInspection */
            $service = $this->service(SpecializedConfigurer::class, $this->svShimContentType, $config);

            return $service;
        }

        return parent::getConfigurer($config);
    }

    /**
     * @param \XFES\Elasticsearch\Api|null $es
     * @return \XFES\Service\Optimizer|SpecializedOptimizer
     */
    protected function getOptimizer(\XFES\Elasticsearch\Api $es = null)
    {
        if ($this->svShimContentType !== '')
        {
            $es = $es ?: $this->getSpecializedSearchIndexRepo()->getIndexApi($this->svShimContentType);
            /** @var SpecializedOptimizer $service */
            /** @noinspection PhpUnnecessaryLocalVariableInspection */
            $service = $this->service(SpecializedOptimizer::class, $this->svShimContentType, $es);

            return $service;
        }

        return parent::getOptimizer($es);
    }

    /**
     * @param \XFES\Elasticsearch\Api|null $es
     * @return \XFES\Service\Analyzer|SpecializedAnalyzer
     */
    protected function getAnalyzer(\XFES\Elasticsearch\Api $es = null)
    {
        if ($this->svShimContentType !== '')
        {
            $es = $es ?: $this->getSpecializedSearchIndexRepo()->getIndexApi($this->svShimContentType);
            /** @var SpecializedAnalyzer $service */
            /** @noinspection PhpUnnecessaryLocalVariableInspection */
            $service = $this->service(SpecializedAnalyzer::class, $this->svShimContentType, $es);

            return $service;
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
        $repo = $this->getSpecializedSearchIndexRepo();
        $handler = $repo->getHandler($contentType, false);
        if ($handler === null)
        {
            throw $this->exception($this->notFound());
        }

        return $handler;
    }

    /**
     * @return \XF\Mvc\Entity\Repository|SpecializedSearchIndex
     */
    protected function getSpecializedSearchIndexRepo(): SpecializedSearchIndex
    {
        return $this->repository('SV\SearchImprovements:SpecializedSearchIndex');
    }
}