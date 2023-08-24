<?php

namespace SV\SearchImprovements\Service\Specialized;

use SV\SearchImprovements\Repository\SpecializedSearchIndex;
use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use function is_array;

class Configurer extends \XFES\Service\Configurer
{
    /** @var string */
    protected $singleType;

    public function __construct(\XF\App $app, string $singleType, $config = null)
    {
        $this->app = $app;
        $this->singleType = $singleType;
        $config = $config ?? [];
        if (is_array($config))
        {
            $config = $this->getSpecializedSearchIndexRepo()->getIndexApi($singleType, $config);
        }

        parent::__construct($app, $config);
    }

    public function purgeIndex()
    {
        if ($this->es->indexExists())
        {
            try
            {
                $this->es->closeIndex();
                $this->es->requestFromIndex('put', '_settings', [
                    'index' => [
                        'blocks' => [
                            'read_only_allow_delete' => false
                        ]
                    ]
                ]);
            }
            catch (\Exception $e)
            {
                // swallow errors trying to get the index into the right state
            }
            $this->es->deleteIndex();
        }
    }

    public function getAnalyzerConfig(): array
    {
        /** @var SpecializedAnalyzer $analyzer */
        $analyzer = $this->service(SpecializedAnalyzer::class, $this->singleType, $this->es);
        return $analyzer->getCurrentConfig();
    }

    public function initializeIndex(array $analyzerConfig)
    {
        $this->purgeIndex();

        /** @var SpecializedAnalyzer $analyzer */
        $analyzer = $this->service(SpecializedAnalyzer::class, $this->singleType, $this->es);
        $analyzerDsl = $analyzer->getAnalyzerFromConfig($analyzerConfig);

        /** @var SpecializedOptimizer $optimizer */
        $optimizer = $this->service(SpecializedOptimizer::class, $this->singleType, $this->es);
        $optimizer->optimize($analyzerDsl);
    }

    protected function getSpecializedSearchIndexRepo(): SpecializedSearchIndex
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('SV\SearchImprovements:SpecializedSearchIndex');
    }
}