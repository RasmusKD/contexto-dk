<?php

namespace App\Core;

use Wamania\Snowball\Stemmer\Danish;

class Lemmatizer
{
    private static ?Danish $stemmer = null;
    private static array $cache = [];

    private static function safeLowercase(string $str): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower(trim($str))
            : strtolower(trim($str));
    }

    public static function lemmatize(string $word): string
    {
        $word = self::safeLowercase($word);

        if (isset(self::$cache[$word])) {
            return self::$cache[$word];
        }

        if (self::$stemmer === null) {
            self::$stemmer = new Danish();
        }

        $stem = self::$stemmer->stem($word);
        self::$cache[$word] = $stem;

        return $stem;
    }

    // Genererer ordvarianter for bedre matching
    public static function tryVariants(string $word): array
    {
        $word = self::safeLowercase($word);
        $baseForm = self::lemmatize($word);
        $variants = [$word, $baseForm];

        // Danske bøjningsmønstre
        $patterns = [
            ['er$', ''], ['er$', 'e'], ['r$', ''], ['e$', ''], ['$', 'e'],
            ['en$', ''], ['et$', ''], ['ne$', ''], ['ene$', ''],
            ['s$', ''], ['ede$', 'e'], ['ede$', ''], ['te$', ''], ['ende$', 'e'],
        ];

        foreach ($patterns as [$pattern, $replacement]) {
            $variant = preg_replace("/$pattern/", $replacement, $word);
            if ($variant !== $word && !in_array($variant, $variants)) {
                $variants[] = $variant;
            }
        }

        return array_unique($variants);
    }
}
