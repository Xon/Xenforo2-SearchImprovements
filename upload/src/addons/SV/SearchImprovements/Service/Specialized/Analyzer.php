<?php

namespace SV\SearchImprovements\Service\Specialized;

use SV\SearchImprovements\Repository\SpecializedSearchIndex as SpecializedSearchIndexRepo;
use SV\SearchImprovements\Search\Specialized\AbstractData;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use SV\StandardLib\Helper;
use XF\App;
use XFES\Elasticsearch\Api;
use function array_values, array_merge, array_key_exists, is_array, array_replace;

/**
 * @Extends \XFES\Service\Analyzer
 */
class Analyzer extends \XFES\Service\Analyzer
{
    /** @var array */
    protected $ngramDefault = [
        'type' => 'edge_ngram',
        'min_gram'    => 2,
        'max_gram'    => 20,
        'token_chars' => [
            'letter',
            'digit',
            'punctuation',
            'symbol',
        ]
    ];

    /** @var int */
    protected $minNgramSize = 1;
    /** @var int */
    protected $maxNgramSize = 32;
    /** @var string */
    protected $singleType;
    /** @var AbstractData|null */
    protected $searchHandler;

    public static function get(string $singleType, ?Api $es = null, ?AbstractData $searchHandler = null): self
    {
        $es = $es ?? SpecializedSearchIndexRepo::get()->getIndexApi($singleType);

        return Helper::service(self::class, \XF::app(), $singleType, $es, $searchHandler);
    }

    public function __construct(App $app, string $singleType, Api $es, ?AbstractData $searchHandler = null)
    {
        $this->singleType = $singleType;
        $this->searchHandler = $searchHandler;
        parent::__construct($app, $es);

        if ($this->getEsApi()->majorVersion() < 7)
        {
            $this->ngramDefault['type'] = 'edgeNGram';
        }
    }

    public function getNgramDefault() : array
    {
        return $this->ngramDefault;
    }

    public function getAnalyzerFromConfig(array $config): array
    {
        $result = parent::getAnalyzerFromConfig($config);

        // always generate analyzer configuration, even if it isn't used
        $nearExactMatchAnalyzer = $result['analysis']['analyzer']['default'];
        $simpleFilter = $nearExactMatchAnalyzer['filter'];
        foreach ($simpleFilter as $key => $value)
        {
            if ($value === 'xf_stop' || $value === 'xf_stemmer')
            {
                unset($simpleFilter[$key]);
            }
        }
        $simpleFilter = array_values($simpleFilter);

        // custom character filters (before tokenization)
        $result['analysis']['char_filter']['sv_strip_white_space'] = [
            'type' => 'pattern_replace',
            'pattern' => '\\s*',
            'replacement' => '',
        ];

        $edgeNgram = $this->getNgramFilter($config['sv_ess_ngram'] ?? []);
        $result['analysis']['filter']['sv_text_edge_ngram_filter'] = $edgeNgram;

        // custom tokenizer
        $result['analysis']['tokenizer']['sv_keyword_ngram_tokenizer'] = [
            'type' => 'ngram',
            'min_gram'    => $edgeNgram['min_gram'],
            'max_gram'    => $edgeNgram['max_gram'],
            'token_chars' => [
                'letter',
                'digit',
                'punctuation',
                'symbol',
            ]
        ];

        // custom filters (after tokenization)
        $result['analysis']['analyzer']['sv_near_exact'] = [
            'type'      => 'custom',
            'tokenizer' => 'standard',
            'filter'    => $simpleFilter,
        ];
        $result['analysis']['analyzer']['sv_near_exact_no_whitespace'] = [
            'type'      => 'custom',
            'char_filter' => [
                'sv_strip_white_space',
            ],
            'tokenizer' => 'standard',
            'filter'    => $simpleFilter,
        ];
        $result['analysis']['analyzer']['sv_keyword_ngram'] = [
            'type'      => 'custom',
            'tokenizer' => 'sv_keyword_ngram_tokenizer',
            'filter'    => $simpleFilter,
        ];
        $result['analysis']['analyzer']['sv_keyword_ngram_no_whitespace'] = [
            'type'      => 'custom',
            'char_filter' => [
                'sv_strip_white_space',
            ],
            'tokenizer' => 'sv_keyword_ngram_tokenizer',
            'filter'    => $simpleFilter,
        ];
        $result['analysis']['analyzer']['sv_text_edge_ngram'] = [
            'type'      => 'custom',
            'tokenizer' => 'standard',
            'filter'    => array_merge($simpleFilter, ['sv_text_edge_ngram_filter']),
        ];

        if ($this->getEsApi()->majorVersion() >= 7)
        {
            $result['index']['max_ngram_diff'] = $this->maxNgramSize;
        }

        return $result;
    }

    public function getDefaultConfig(): array
    {
        $defaultConfig = parent::getDefaultConfig();

        if (!array_key_exists('sv_ess_ngram', $defaultConfig))
        {
            $defaultConfig['sv_ess_ngram'] = $this->getNgramDefault();
        }

        return $defaultConfig;
    }

    public function getConfigFromAnalyzer(array $analysis): array
    {
        $currentConfig = parent::getConfigFromAnalyzer($analysis);

        $ngramFilter = $analysis['filter']['sv_text_edge_ngram_filter'] ?? null;
        if (is_array($ngramFilter))
        {
            $currentConfig['sv_ess_ngram'] = $this->getNgramFilter($ngramFilter);
        }

        return $currentConfig;
    }

    public function getNgramFilter(array $ngramVars) : array
    {
        $defaultNgram = $this->getNgramDefault();
        $ngramVars = array_replace($defaultNgram, $ngramVars);

        $sizeSanitizer = function ($size, int $default) {
            $finalSize = (int)$size;

            if ($finalSize < $this->minNgramSize || $finalSize > $this->maxNgramSize)
            {
                return $default;
            }

            return $finalSize;
        };

        $ngramVars['min_gram'] = $sizeSanitizer((int)$ngramVars['min_gram'], (int)$defaultNgram['min_gram']);
        $ngramVars['max_gram'] = $sizeSanitizer((int)$ngramVars['max_gram'], (int)$defaultNgram['max_gram']);

        return $ngramVars;
    }

    public function updateAnalyzer(array $config)
    {
        $settings = $this->getAnalyzerFromConfig($config);

        if (!$this->es->indexExists())
        {
            $optimizer = SpecializedOptimizer::get($this->singleType, $this->es, $this->searchHandler);
            $optimizer->optimize($settings, true);

            return;
        }

        parent::updateAnalyzer($config);
    }
}