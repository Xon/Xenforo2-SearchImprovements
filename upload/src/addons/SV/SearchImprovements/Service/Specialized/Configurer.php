<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Service\Specialized;

use SV\SearchImprovements\Repository\SpecializedSearchIndex as SpecializedSearchIndexRepo;
use SV\SearchImprovements\Search\Specialized\AbstractData;
use SV\SearchImprovements\Service\Specialized\Analyzer as SpecializedAnalyzer;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use SV\StandardLib\Helper;
use XF\App;
use function is_array;

class Configurer extends \XFES\Service\Configurer
{
    /** @var string */
    protected $singleType;
    /** @var AbstractData|null */
    protected $searchHandler;

    public static function get(string $singleType, $config = null, ?AbstractData $searchHandler = null): self
    {
        return Helper::service(self::class, \XF::app(), $singleType, $config, $searchHandler);
    }

    public function __construct(App $app, string $singleType, $config = null, ?AbstractData $searchHandler = null)
    {
        $this->app = $app;
        $this->singleType = $singleType;
        $this->searchHandler = $searchHandler;
        $config = $config ?? [];
        if (is_array($config))
        {
            $config = SpecializedSearchIndexRepo::get()->getIndexApi($singleType, $config);
        }

        parent::__construct($app, $config);
    }

    public function purgeIndex(): void
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
        return SpecializedAnalyzer::get($this->singleType, $this->es, $this->searchHandler)->getCurrentConfig();
    }

    public function initializeIndex(array $analyzerConfig): void
    {
        $this->purgeIndex();

        $analyzer = SpecializedAnalyzer::get($this->singleType, $this->es, $this->searchHandler);
        $analyzerDsl = $analyzer->getAnalyzerFromConfig($analyzerConfig);

        $optimizer = SpecializedOptimizer::get($this->singleType, $this->es, $this->searchHandler);
        $optimizer->optimize($analyzerDsl);
    }
}