<?php

namespace SV\SearchImprovements\Service\Specialized;

class Configurer extends \XFES\Service\Configurer
{
    /** @var string */
    protected $singleType;

    public function __construct(\XF\App $app, string $singleType, $config = null)
    {
        $this->singleType = $singleType;
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
        /** @var Analyzer $analyzer */
        $analyzer = $this->service('SV\SearchImprovements:Specialized\Analyzer', $this->singleType, $this->es);
        return $analyzer->getCurrentConfig();
    }

    public function initializeIndex(array $analyzerConfig)
    {
        $this->purgeIndex();

        /** @var Analyzer $analyzer */
        $analyzer = $this->service('SV\SearchImprovements:Specialized\Analyzer', $this->singleType, $this->es);
        $analyzerDsl = $analyzer->getAnalyzerFromConfig($analyzerConfig);

        /** @var Optimizer $optimizer */
        $optimizer = $this->service('SV\SearchImprovements:Specialized\Optimizer', $this->singleType, $this->es);
        $optimizer->optimize($analyzerDsl);
    }
}