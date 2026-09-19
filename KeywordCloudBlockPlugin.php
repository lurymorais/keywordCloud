<?php

/**
 * @file plugins/blocks/keywordCloud/KeywordCloudBlockPlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class KeywordCloudBlockPlugin
 *
 * @brief Class for KeywordCloud block plugin
 */

namespace APP\plugins\blocks\keywordCloud;

use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\controlledVocab\ControlledVocab;
use PKP\facades\Locale;
use PKP\plugins\BlockPlugin;

class KeywordCloudBlockPlugin extends BlockPlugin
{
    private const KEYWORD_BLOCK_MAX_ITEMS = 50;
    private const KEYWORD_BLOCK_CACHE_DAYS = 2;

    public function getDisplayName(): string
    {
        return __('plugins.block.keywordCloud.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.block.keywordCloud.description');
    }

    public function getContextSpecificPluginSettingsFile(): string
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    public function cacheDismiss()
    {
        return null;
    }

    public function getContents($templateMgr, $request = null)
    {
        $context = $request->getContext();
        if (!$context) {
            return '';
        }

        $locale = Locale::getLocale();
        $primaryLocale = Locale::getPrimaryLocale();

        $keywords = $this->getCachedKeywords($context, $locale);
        if ($keywords == '[]') {
            $keywords = $this->getCachedKeywords($context, $primaryLocale);
        }

        $templateMgr->addJavaScript('d3', 'https://d3js.org/d3.v4.js');
        $templateMgr->addJavaScript(
            'd3-cloud',
            'https://cdn.jsdelivr.net/gh/holtzy/D3-graph-gallery@master/LIB/d3.layout.cloud.js'
        );

        $templateMgr->assign('keywords', $keywords);
        return parent::getContents($templateMgr, $request);
    }

    private function getCachedKeywords(Context $context, string $locale): ?string
    {
        $cacheKey = 'keywordCloud_' . $context->getId() . '_' . $locale;
        $expiration = \DateInterval::createFromDateString(self::KEYWORD_BLOCK_CACHE_DAYS . ' days');

        return Cache::remember($cacheKey, $expiration, function () use ($context, $locale) {
            return $this->getJournalKeywords($context->getId(), $locale);
        });
    }

    private function getJournalKeywords(int $journalId, string $locale): string
    {
        try {
            $rows = DB::table('controlled_vocabs as cv')
                ->join(
                    'controlled_vocab_entries as cve',
                    'cve.controlled_vocab_id',
                    '=',
                    'cv.controlled_vocab_id'
                )
                ->join(
                    'controlled_vocab_entry_settings as cves',
                    'cves.controlled_vocab_entry_id',
                    '=',
                    'cve.controlled_vocab_entry_id'
                )
                ->join('publications as p', 'p.publication_id', '=', 'cv.assoc_id')
                ->join('submissions as s', 's.submission_id', '=', 'p.submission_id')
                ->where('cv.symbolic', ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD)
                ->where('cv.assoc_type', Application::ASSOC_TYPE_PUBLICATION)
                ->where('s.context_id', $journalId)
                ->where('s.status', Submission::STATUS_PUBLISHED)
                ->where('cves.locale', $locale)
                ->where('cves.setting_name', 'name')
                ->pluck('cves.setting_value')
                ->toArray();
        } catch (\Throwable $e) {
            error_log('KeywordCloud query error: ' . $e->getMessage());
            return '[]';
        }

        if (empty($rows)) {
            return '[]';
        }

        $counts = [];
        foreach ($rows as $keyword) {
            $normalized = strtolower(trim((string) $keyword));
            if ($normalized === '') {
                continue;
            }
            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        if (empty($counts)) {
            return '[]';
        }

        arsort($counts, SORT_NUMERIC);
        $topKeywords = array_slice($counts, 0, self::KEYWORD_BLOCK_MAX_ITEMS, true);

        $keywords = [];
        foreach ($topKeywords as $text => $size) {
            $keywords[] = (object) ['text' => $text, 'size' => $size];
        }

        return json_encode($keywords);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias(
        '\APP\plugins\blocks\keywordCloud\KeywordCloudBlockPlugin',
        '\KeywordCloudBlockPlugin'
    );
}
