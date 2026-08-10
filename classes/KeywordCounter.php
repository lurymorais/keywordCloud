<?php

namespace APP\plugins\blocks\keywordCloud\classes;

class KeywordCounter
{
    /** @var list<string> */
    private array $keywordNames = [];

    /**
     * @param list<array{name: string}> $keywords
     */
    public function addPublication(array $keywords): void
    {
        $names = array_map('strtolower', array_column($keywords, 'name'));
        $this->keywordNames = array_merge($this->keywordNames, array_unique($names));
    }

    /**
     * @return array<string|int, int>
     */
    public function getMostFrequent(int $limit): array
    {
        $counts = array_count_values($this->keywordNames);
        arsort($counts, SORT_NUMERIC);

        return array_slice($counts, 0, $limit, true);
    }
}
