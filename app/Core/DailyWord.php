<?php
// app/Core/DailyWord.php

namespace App\Core;

class DailyWord
{
    private const START_DATE = '2025-05-14';
    private static ?array $wordSchedule = null;

    private static function loadWordSchedule(): array
    {
        if (self::$wordSchedule === null) {
            $wordsPath = __DIR__ . '/../Config/words.php';
            if (file_exists($wordsPath)) {
                self::$wordSchedule = require $wordsPath;
            } else {
                error_log("Words configuration file not found: $wordsPath");
                self::$wordSchedule = [];
            }
        }
        return self::$wordSchedule;
    }

    public static function getWordForDate(?string $dateString = null): ?string
    {
        $targetDate = new \DateTime($dateString ?? 'now', new \DateTimeZone('UTC'));
        $startDate = new \DateTime(self::START_DATE, new \DateTimeZone('UTC'));
        $today = new \DateTime('now', new \DateTimeZone('UTC'));

        // Ingen fremtidige ord eller ord før startdato
        if ($targetDate > $today || $targetDate < $startDate) {
            return null;
        }

        $daysSinceStart = (int)$startDate->diff($targetDate)->days;
        $schedule = self::loadWordSchedule();

        return $schedule[$daysSinceStart] ?? null;
    }

    public static function getTodaysWord(): ?string
    {
        return self::getWordForDate();
    }

    public static function getGameNumber(?string $dateString = null): int
    {
        $targetDate = new \DateTime($dateString ?? 'now', new \DateTimeZone('UTC'));
        $startDate = new \DateTime(self::START_DATE, new \DateTimeZone('UTC'));

        if ($targetDate < $startDate) {
            return 0;
        }

        return (int)$startDate->diff($targetDate)->days + 1; // 1-baseret nummerering
    }

    // Returnerer tidligere planlagte ord med datoer (kun til i dag)
    public static function getPastScheduledWordsWithDates(): array
    {
        $scheduled = [];
        $currentDate = new \DateTime(self::START_DATE, new \DateTimeZone('UTC'));
        $today = new \DateTime('now', new \DateTimeZone('UTC'));
        $schedule = self::loadWordSchedule();

        foreach ($schedule as $daysOffset => $word) {
            $dateKey = clone $currentDate;
            if ($daysOffset > 0) {
                $dateKey->modify("+" . $daysOffset . " days");
            }

            if ($dateKey <= $today) {
                $scheduled[$dateKey->format('Y-m-d')] = $word;
            }
        }

        return $scheduled;
    }
}
