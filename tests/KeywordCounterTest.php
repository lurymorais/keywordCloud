<?php

namespace APP\plugins\blocks\keywordCloud\tests;

use APP\plugins\blocks\keywordCloud\classes\KeywordCounter;
use PHPUnit\Framework\TestCase;

class KeywordCounterTest extends TestCase
{
    public function testCountsEachKeywordOncePerPublicationAndPreservesNumericKeywords(): void
    {
        $counter = new KeywordCounter();

        $counter->addPublication([
            ['name' => 'OJS'],
            ['name' => 'ojs'],
            ['name' => '2020'],
        ]);
        $counter->addPublication([
            ['name' => 'OJS'],
            ['name' => 'Publishing'],
        ]);

        self::assertSame(
            [
                'ojs' => 2,
                2020 => 1,
                'publishing' => 1,
            ],
            $counter->getMostFrequent(50)
        );
    }

    public function testReturnsOnlyTheMostFrequentKeywordsUpToTheLimit(): void
    {
        $counter = new KeywordCounter();
        $counter->addPublication([
            ['name' => 'first'],
            ['name' => 'second'],
            ['name' => 'third'],
        ]);
        $counter->addPublication([
            ['name' => 'first'],
            ['name' => 'second'],
        ]);
        $counter->addPublication([
            ['name' => 'first'],
        ]);

        self::assertSame(
            [
                'first' => 3,
                'second' => 2,
            ],
            $counter->getMostFrequent(2)
        );
    }
}
