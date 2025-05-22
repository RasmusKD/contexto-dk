<?php

namespace App\Models;

use App\Core\Database;
use MongoDB\Collection;

class GameModel
{
    private Collection $collection;
    private array $gameDataCache = []; // Memory cache for performance

    public function __construct()
    {
        $this->collection = Database::getCollection('games');
    }

    public function getGameDataByWord(string $word): ?object
    {
        $searchWord = strtolower($word);

        // Returner fra cache hvis tilgængelig
        if (isset($this->gameDataCache[$searchWord])) {
            return $this->gameDataCache[$searchWord];
        }

        // Brug projection for at begrænse data - vi behøver ikke alle similar_items her
        $document = $this->collection->findOne(
            ['_id' => $searchWord],
            ['projection' => ['_id' => 1, 'has_similar_items' => 1]]
        );

        if ($document) {
            $this->gameDataCache[$searchWord] = $document;
        }

        return $document;
    }

    public function getTotalWordCount(string $word): int
    {
        $searchWord = strtolower($word);

        try {
            // Prøv først cached count
            $document = $this->collection->findOne(
                ['_id' => $searchWord],
                ['projection' => ['similar_items_count' => 1]]
            );

            if ($document && isset($document->similar_items_count)) {
                return (int)$document->similar_items_count;
            }

            // Fallback: tæl similar_items og cache resultatet
            $document = $this->collection->findOne(
                ['_id' => $searchWord],
                ['projection' => ['similar_items' => 1]]
            );

            if ($document && isset($document->similar_items) && $document->similar_items instanceof \MongoDB\Model\BSONArray) {
                $count = count($document->similar_items);

                // Cache count for fremtidig brug
                $this->collection->updateOne(
                    ['_id' => $searchWord],
                    ['$set' => ['similar_items_count' => $count]]
                );

                return $count;
            }

            return 0;
        } catch (\Exception $e) {
            error_log("getTotalWordCount fejl: " . $e->getMessage());
            return 0;
        }
    }

    public function getWordRankAndScore(string $targetWord, string $guessWord): ?array
    {
        $targetWord = strtolower($targetWord);
        $guessWord = strtolower($guessWord);

        // Eksakt match = korrekt svar
        if ($targetWord === $guessWord) {
            return [
                'rank' => 1,
                'score' => 1.0,
                'is_correct' => true
            ];
        }

        try {
            $document = $this->collection->findOne(['_id' => $targetWord]);

            if (!$document || !isset($document->similar_items)) {
                return null;
            }

            // Søg gennem similar_items for match
            foreach ($document->similar_items as $index => $item) {
                if ($item instanceof \MongoDB\Model\BSONArray && count($item) >= 2) {
                    $word = strtolower((string)$item[0]);
                    if ($word === $guessWord) {
                        return [
                            'rank' => $index + 2, // +2 for zero-index og target word
                            'score' => (float)$item[1]
                        ];
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            error_log("getWordRankAndScore fejl: " . $e->getMessage());
            return null;
        }
    }

    public function getRandomWord($lastWord = null): ?string
    {
        // Prøv op til 3 gange for at undgå samme ord som sidste
        for ($i = 0; $i < 3; $i++) {
            $cursor = $this->collection->aggregate([
                ['$sample' => ['size' => 1]],
                ['$project' => ['_id' => 1]]
            ]);

            foreach ($cursor as $document) {
                $randomWord = $document['_id'];

                // Hvis vi har et lastWord og fik det samme, prøv igen
                if ($lastWord && $randomWord === $lastWord && $i < 2) {
                    continue;
                }

                return $randomWord;
            }
        }

        return null;
    }

    public function getFullWordDocument(string $word): ?object
    {
        $word = strtolower($word);

        try {
            return $this->collection->findOne(['_id' => $word]);
        } catch (\Exception $e) {
            error_log("getFullWordDocument fejl: " . $e->getMessage());
            return null;
        }
    }
}
