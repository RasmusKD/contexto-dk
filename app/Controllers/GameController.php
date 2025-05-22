<?php
// app/Controllers/GameController.php

namespace App\Controllers;

use App\Core\DailyWord;
use App\Core\Lemmatizer;
use App\Models\GameModel;

class GameController
{
    private GameModel $gameModel;

    public function __construct()
    {
        $this->gameModel = new GameModel();
    }

    private function loadView(string $viewName, array $data = [])
    {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $viewName . '.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            error_log("FEJL: View '$viewName' blev ikke fundet på stien: $viewPath");
            echo "Fejl: View '$viewName' blev ikke fundet.";
        }
    }

    private function getCurrentTargetWord(): ?string
    {
        if (isset($_GET['date'])) {
            $requestedDate = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
            error_log("GameController::getCurrentTargetWord() - Henter ord for specifik dato: " . $requestedDate);
            $word = DailyWord::getWordForDate($requestedDate);
            error_log("GameController::getCurrentTargetWord() - Ord for dato '" . $requestedDate . "': " . ($word ?? 'NULL'));
            return $word;
        }

        $sessionWord = $_SESSION['target_word'] ?? null;
        if ($sessionWord) {
            error_log("GameController::getCurrentTargetWord() - Bruger target_word fra session: " . $sessionWord);
            return $sessionWord;
        }

        $todaysWord = DailyWord::getTodaysWord();
        error_log("GameController::getCurrentTargetWord() - Henter dagens ord: " . ($todaysWord ?? 'NULL'));
        return $todaysWord;
    }

    public function index()
    {
        error_log("GameController::index() - Kaldes.");
        $currentTargetWord = $this->getCurrentTargetWord();
        $requestedDate = $_GET['date'] ?? null;
        error_log("GameController::index() - currentTargetWord: " . ($currentTargetWord ?? 'NULL'));

        if (!$currentTargetWord) {
            $_SESSION['message'] = "Der er intet spil defineret for i dag/den valgte dato.";
            error_log("GameController::index() - Intet target ord, rydder session.");
            unset($_SESSION['target_word'], $_SESSION['guesses'], $_SESSION['game_won'], $_SESSION['word_list_count']);
        } elseif (!isset($_SESSION['target_word']) || $_SESSION['target_word'] !== $currentTargetWord) {
            error_log("GameController::index() - Starter ny spilsession for: " . $currentTargetWord);
            $this->startNewGameSession($currentTargetWord);
        } else {
            error_log("GameController::index() - Fortsætter eksisterende spilsession for: " . $_SESSION['target_word']);
        }

        // Hent tidligere spil med datoer og beregn spilnumre
        $pastGames = DailyWord::getPastScheduledWordsWithDates();
        $pastGamesWithNumbers = [];

        foreach ($pastGames as $date => $word) {
            $pastGamesWithNumbers[$date] = [
                'word' => $word,
                'gameNumber' => DailyWord::getGameNumber($date)
            ];
        }

        // Sorter gæt - korrekt gæt først
        if (isset($_SESSION['guesses']) && !empty($_SESSION['guesses'])) {
            $this->sortGuesses();
        }

        $viewData = [
            'targetWordForDisplay' => $_SESSION['target_word'] ?? 'Intet spil aktivt',
            'guesses' => $_SESSION['guesses'] ?? [],
            'gameWon' => $_SESSION['game_won'] ?? false,
            'gameGivenUp' => $_SESSION['game_given_up'] ?? false,
            'message' => $_SESSION['message'] ?? null,
            'totalWordsInList' => $_SESSION['word_list_count'] ?? 0,
            'gameNumber' => $_SESSION['is_random_game'] ?? false
                ? ($_SESSION['random_game_number'] ?? 'N/A')
                : (DailyWord::getGameNumber($_GET['date'] ?? null) ?? 0),
            'guessCount' => isset($_SESSION['guesses']) ? count($_SESSION['guesses']) : 0,
            'pastGamesWithNumbers' => $pastGamesWithNumbers ?? [],
            'isRandomGame' => $_SESSION['is_random_game'] ?? false
        ];

        unset($_SESSION['message']);
        $this->loadView('game/play', $viewData);
    }

    private function startNewGameSession(string $wordToPlay)
    {
        error_log("GameController::startNewGameSession() - Starter for ord: '" . $wordToPlay . "'");

        $totalWordCount = $this->gameModel->getTotalWordCount($wordToPlay);

        $_SESSION['target_word'] = $wordToPlay;
        $_SESSION['word_list_count'] = $totalWordCount + 1; // +1 for target ordet
        $_SESSION['guesses'] = [];
        $_SESSION['game_won'] = false;
        $_SESSION['game_given_up'] = false;
        $_SESSION['hint_used'] = false;

        error_log("GameController::startNewGameSession() - Session sat for '" . $wordToPlay . "'. Total list count: " . $_SESSION['word_list_count']);
    }

    public function newGame()
    {
        error_log("GameController::newGame() - Kaldes.");
        $todaysWord = DailyWord::getTodaysWord();

        if ($todaysWord) {
            error_log("GameController::newGame() - Dagens ord er: " . $todaysWord);
            // Ryd gammel session før start af nyt spil
            unset($_SESSION['target_word'], $_SESSION['guesses'], $_SESSION['game_won'],
                $_SESSION['word_list_count'], $_SESSION['is_random_game'], $_SESSION['hint_used']);
            $this->startNewGameSession($todaysWord);
        } else {
            $_SESSION['message'] = "Intet spil defineret for i dag.";
            error_log("GameController::newGame() - Intet spil defineret for i dag.");
        }

        header('Location: /');
        exit;
    }

    public function handleGuess()
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        error_log("GameController::handleGuess() - Kaldes. AJAX: " . ($isAjax ? 'Ja' : 'Nej'));

        if (!isset($_SESSION['target_word']) || ($_SESSION['game_won'] ?? false)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Intet aktivt spil eller spil allerede vundet.']);
                return;
            } else {
                error_log("GameController::handleGuess() - Intet aktivt spil eller spil vundet. Omdirigerer.");
                header('Location: /');
                exit;
            }
        }

        // Hent gæt fra POST data eller JSON input
        $guess = '';
        if ($isAjax) {
            $input = json_decode(file_get_contents('php://input'), true);
            $guess = strtolower(trim($input['guess'] ?? ''));
        } else {
            $guess = strtolower(trim($_POST['guess'] ?? ''));
        }

        error_log("GameController::handleGuess() - Brugerens gæt: '" . $guess . "'");

        $response = [
            'message' => null,
            'messageType' => 'info',
            'gameWon' => false
        ];

        if (empty($guess)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Indtast venligst et gæt.']);
                return;
            } else {
                $_SESSION['message'] = "Indtast venligst et gæt.";
                error_log("GameController::handleGuess() - Tomt gæt.");
            }
        } else {
            $targetWord = $_SESSION['target_word'];
            error_log("GameController::handleGuess() - Target: '" . $targetWord . "'");

            // Tjek om gæt allerede er foretaget
            $guessAlreadyMade = false;
            $existingGuessIndex = null;

            foreach($_SESSION['guesses'] as $index => $g) {
                if ($g['word'] === $guess) {
                    $guessAlreadyMade = true;
                    $existingGuessIndex = $index;
                    break;
                }
            }

            // RETTELSE: Kun eksakt match for korrekte gæt
            $normalizedGuess = strtolower(trim($guess));
            $normalizedTarget = strtolower(trim($targetWord));

            if ($normalizedGuess === $normalizedTarget) {
                $_SESSION['game_won'] = true;
                $response['gameWon'] = true;
                error_log("GameController::handleGuess() - Korrekt gæt!");

                if (!$guessAlreadyMade) {
                    $_SESSION['guesses'][] = [
                        'word' => $guess,
                        'rank' => 1,
                        'is_correct' => true,
                        'timestamp' => time()
                    ];
                } else {
                    // Opdater timestamp for eksisterende korrekt gæt
                    $_SESSION['guesses'][$existingGuessIndex]['timestamp'] = time();
                    $_SESSION['guesses'][$existingGuessIndex]['duplicate'] = true;
                }
            } else {
                // Ikke korrekt gæt - søg på listen
                $wordInfo = $this->getWordInfo($targetWord, $guess);
                $actualMatchedWord = null;

                // Hvis ikke fundet, prøv lemmatiserede varianter (kun for at finde på listen)
                if (!$wordInfo) {
                    $variants = Lemmatizer::tryVariants($guess);

                    foreach ($variants as $variant) {
                        if ($variant === $guess) continue;

                        $wordInfo = $this->getWordInfo($targetWord, $variant);
                        if ($wordInfo) {
                            $actualMatchedWord = $variant;
                            // Tjek om vi allerede har gættet denne lemmatiserede form
                            foreach($_SESSION['guesses'] as $index => $g) {
                                if ($g['word'] === $variant) {
                                    $guessAlreadyMade = true;
                                    $existingGuessIndex = $index;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                }

                if ($actualMatchedWord) {
                    $message = "Vi fandt '" . htmlspecialchars($actualMatchedWord) .
                        "' baseret på dit gæt '" . htmlspecialchars($guess) . "'";

                    if ($isAjax) {
                        $response['message'] = $message;
                        $response['messageType'] = 'info';
                    } else {
                        $_SESSION['message'] = $message;
                    }

                    $guess = $actualMatchedWord;
                }

                if ($guessAlreadyMade) {
                    $_SESSION['guesses'][$existingGuessIndex]['timestamp'] = time();
                    $_SESSION['guesses'][$existingGuessIndex]['duplicate'] = true;
                    error_log("GameController::handleGuess() - Gæt '" . $guess . "' var allerede foretaget. Markerer som duplikat.");
                } else if ($wordInfo) {
                    $rank = $wordInfo['rank'];
                    error_log("GameController::handleGuess() - Gæt '" . $guess . "' fundet med rang: " . $rank);

                    $_SESSION['guesses'][] = [
                        'word' => $guess,
                        'rank' => $rank,
                        'is_correct' => false,
                        'timestamp' => time()
                    ];
                } else {
                    error_log("GameController::handleGuess() - Gæt '" . $guess . "' IKKE fundet, heller ikke efter lemmatisering.");

                    $_SESSION['guesses'][] = [
                        'word' => $guess,
                        'rank' => null,
                        'is_correct' => false,
                        'not_found' => true,
                        'timestamp' => time()
                    ];
                }
            }

            $this->sortGuesses();
        }

        // Håndter AJAX response
        if ($isAjax) {
            $latestTimestamp = $this->getLatestTimestamp();
            $latestGuess = $this->findLatestGuess($latestTimestamp);
            $allGuesses = $this->formatAllGuesses($latestTimestamp);

            $response['latestGuess'] = $latestGuess;
            $response['allGuesses'] = $allGuesses;
            $response['guessCount'] = count($_SESSION['guesses']);

            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        } else {
            header('Location: /');
            exit;
        }
    }

    // Hjælpemetode til at sortere gæt
    private function sortGuesses()
    {
        usort($_SESSION['guesses'], function ($a, $b) {
            if (isset($a['is_correct']) && $a['is_correct']) return -1;
            if (isset($b['is_correct']) && $b['is_correct']) return 1;
            if ((!isset($a['rank']) || $a['rank'] === null) && (!isset($b['rank']) || $b['rank'] === null)) return 0;
            if (!isset($a['rank']) || $a['rank'] === null) return 1;
            if (!isset($b['rank']) || $b['rank'] === null) return -1;
            return $a['rank'] <=> $b['rank'];
        });
    }

    // Hjælpemetode til at finde seneste timestamp
    private function getLatestTimestamp(): int
    {
        $latestTimestamp = 0;
        foreach($_SESSION['guesses'] as $g) {
            if (isset($g['timestamp']) && $g['timestamp'] > $latestTimestamp) {
                $latestTimestamp = $g['timestamp'];
            }
        }
        return $latestTimestamp;
    }

    // Hjælpemetode til at finde seneste gæt
    private function findLatestGuess(int $latestTimestamp): ?array
    {
        foreach($_SESSION['guesses'] as $g) {
            if (isset($g['timestamp']) && $g['timestamp'] === $latestTimestamp) {
                return $this->formatGuessForDisplay($g, $latestTimestamp);
            }
        }
        return null;
    }

    // Hjælpemetode til at formatere alle gæt
    private function formatAllGuesses(int $latestTimestamp): array
    {
        $allGuesses = [];
        foreach($_SESSION['guesses'] as $g) {
            if (!(isset($g['not_found']) && $g['not_found'])) {
                $allGuesses[] = $this->formatGuessForDisplay($g, $latestTimestamp);
            }
        }
        return $allGuesses;
    }

    // Hjælpemetode til at formatere gæt til visning i JSON
    private function formatGuessForDisplay($guess, $latestTimestamp): array
    {
        $bgColor = "rgba(200, 200, 200, 0.1)";
        $barColor = "var(--gray)";
        $widthPercentage = "0%";

        if (isset($guess['not_found']) && $guess['not_found']) {
            $bgColor = "rgba(249, 24, 128, 0.1)";
            $barColor = "var(--red)";
            $widthPercentage = "0%";
        } else if (isset($guess['rank'])) {
            $rank = $guess['rank'];
            $totalWordsInList = $_SESSION['word_list_count'] ?? 10000;

            if ($rank === 1) {
                $bgColor = "rgba(0, 186, 124, 0.1)";
                $barColor = "var(--green)";
                $widthPercentage = "100%";
            } else {
                $maxRank = min($totalWordsInList, 10000);
                $rankRatio = ($rank - 1) / $maxRank;
                $percentage = max(1, 100 * exp(-5 * $rankRatio));
                $widthPercentage = min(100, $percentage) . "%";

                if ($rank <= 300) {
                    $bgColor = "rgba(0, 186, 124, 0.1)";
                    $barColor = "var(--green)";
                } elseif ($rank <= 3000) {
                    $bgColor = "rgba(239, 125, 49, 0.1)";
                    $barColor = "var(--yellow)";
                } else {
                    $bgColor = "rgba(249, 24, 128, 0.1)";
                    $barColor = "var(--red)";
                }
            }
        }

        return [
            'word' => htmlspecialchars($guess['word']),
            'rank' => $guess['rank'] ?? null,
            'is_correct' => $guess['is_correct'] ?? false,
            'not_found' => $guess['not_found'] ?? false,
            'duplicate' => $guess['duplicate'] ?? false,
            'is_hint' => $guess['is_hint'] ?? false,
            'isLatest' => isset($guess['timestamp']) && $guess['timestamp'] === $latestTimestamp,
            'bgColor' => $bgColor,
            'barColor' => $barColor,
            'widthPercentage' => $widthPercentage,
            'showInAllGuesses' => !(isset($guess['not_found']) && $guess['not_found'])
        ];
    }

    // Slå ord information op direkte fra modellen
    private function getWordInfo(string $targetWord, string $guessWord): ?array
    {
        return $this->gameModel->getWordRankAndScore($targetWord, $guessWord);
    }

    public function randomGame()
    {
        error_log("GameController::randomGame() - Genererer et tilfældigt spil.");

        // Ryd eksisterende spilsession
        unset($_SESSION['target_word'], $_SESSION['guesses'], $_SESSION['game_won'],
            $_SESSION['word_list_count'], $_SESSION['hint_used']);

        $lastRandomWord = $_SESSION['last_random_word'] ?? null;
        $randomWord = $this->gameModel->getRandomWord($lastRandomWord);

        if ($randomWord) {
            error_log("GameController::randomGame() - Tilfældigt ord valgt: " . $randomWord);
            $this->startNewGameSession($randomWord);

            $randomGameNumber = $this->generateRandomGameNumber($randomWord);
            $_SESSION['random_game_number'] = $randomGameNumber;
            $_SESSION['last_random_word'] = $randomWord;
            $_SESSION['is_random_game'] = true;
        } else {
            $_SESSION['message'] = "Kunne ikke starte et tilfældigt spil. Prøv igen.";
            error_log("GameController::randomGame() - Kunne ikke hente tilfældigt ord.");
        }

        header('Location: /');
        exit;
    }

    // Hjælpemetode til at generere konsistent men unikt spilnummer for tilfældige spil
    private function generateRandomGameNumber($word): int
    {
        $wordHash = substr(md5($word), 0, 8);
        $decimal = hexdec($wordHash);
        return 10000 + ($decimal % 90000);
    }

    public function getTopWords()
    {
        if (!isset($_SESSION['game_won']) || !$_SESSION['game_won'] || !isset($_SESSION['target_word'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Spil ikke vundet endnu']);
            return;
        }

        $targetWord = $_SESSION['target_word'];

        try {
            $document = $this->gameModel->getFullWordDocument($targetWord);

            if (!$document || !isset($document->similar_items)) {
                http_response_code(404);
                echo json_encode(['error' => 'Orddata ikke fundet']);
                return;
            }

            // Tilføj target ordet som rank 1
            $topWords = [
                [
                    'rank' => 1,
                    'word' => $targetWord,
                    'score' => 1.0
                ]
            ];

            // Udtræk de næste 499 ord (total 500 inkl. target)
            $count = 0;
            foreach ($document->similar_items as $index => $item) {
                if ($count >= 499) break;

                if ($item instanceof \MongoDB\Model\BSONArray && count($item) >= 2) {
                    $word = (string)$item[0];
                    $score = (float)$item[1];

                    $topWords[] = [
                        'rank' => $index + 2,
                        'word' => $word,
                        'score' => $score
                    ];
                    $count++;
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['words' => $topWords]);
        } catch (\Exception $e) {
            error_log("Error fetching top words: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Serverfejl']);
        }
    }

    public function getHint()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['target_word']) || ($_SESSION['game_won'] ?? false)) {
            echo json_encode(['error' => 'Intet aktivt spil eller spil allerede vundet.']);
            return;
        }

        $targetWord = $_SESSION['target_word'];

        // Find bedste gæt hidtil (laveste rank udover 1)
        $bestRank = PHP_INT_MAX;
        foreach ($_SESSION['guesses'] as $guess) {
            if (isset($guess['rank']) && $guess['rank'] > 1 && $guess['rank'] < $bestRank) {
                $bestRank = $guess['rank'];
            }
        }

        // Hvis bedste rank er 2, giv ikke hint da det er for tæt på svaret
        if ($bestRank == 2) {
            echo json_encode(['error' => 'Du er allerede meget tæt på svaret!']);
            return;
        }

        // Beregn target rank for hint
        $targetRank = min(
            $bestRank - 1,
            max(2, floor($bestRank / 2)),
            299
        );

        $document = $this->gameModel->getFullWordDocument($targetWord);

        if (!$document || !isset($document->similar_items)) {
            echo json_encode(['error' => 'Kunne ikke generere et hint.']);
            return;
        }

        $hintWord = $this->findHintWord($document, $targetRank, $bestRank);

        if ($hintWord) {
            $_SESSION['hint_used'] = true;
            $wordInfo = $this->getWordInfo($targetWord, $hintWord['word']);

            if ($wordInfo) {
                $_SESSION['guesses'][] = [
                    'word' => $hintWord['word'],
                    'rank' => $wordInfo['rank'],
                    'is_correct' => false,
                    'timestamp' => time(),
                    'is_hint' => true
                ];

                $this->sortGuesses();

                $latestTimestamp = time();
                $latestGuess = $this->formatGuessForDisplay([
                    'word' => $hintWord['word'],
                    'rank' => $wordInfo['rank'],
                    'is_correct' => false,
                    'timestamp' => $latestTimestamp,
                    'is_hint' => true
                ], $latestTimestamp);

                $allGuesses = $this->formatAllGuesses($latestTimestamp);

                echo json_encode([
                    'success' => true,
                    'latestGuess' => $latestGuess,
                    'allGuesses' => $allGuesses,
                    'guessCount' => count($_SESSION['guesses'])
                ]);
                return;
            }
        }

        echo json_encode(['error' => 'Kunne ikke finde et passende hint.']);
    }

    // Hjælpemetode til at finde hint ord
    private function findHintWord($document, int $targetRank, int $bestRank): ?array
    {
        $wiggleRoom = 5;
        $maxWiggleRoom = 50;

        while ($wiggleRoom <= $maxWiggleRoom) {
            $lowerBound = max(2, $targetRank - $wiggleRoom);
            $upperBound = min($targetRank + $wiggleRoom, $bestRank - 1, 299);

            $potentialHints = [];

            foreach ($document->similar_items as $index => $item) {
                $rank = $index + 2;

                if ($rank >= $lowerBound && $rank <= $upperBound) {
                    if ($item instanceof \MongoDB\Model\BSONArray && count($item) >= 2) {
                        $word = (string)$item[0];

                        $alreadyGuessed = false;
                        foreach ($_SESSION['guesses'] as $guess) {
                            if (strtolower($guess['word']) === strtolower($word)) {
                                $alreadyGuessed = true;
                                break;
                            }
                        }

                        if (!$alreadyGuessed) {
                            $potentialHints[] = [
                                'word' => $word,
                                'rank' => $rank
                            ];
                        }
                    }
                }
            }

            if (!empty($potentialHints)) {
                $randomIndex = array_rand($potentialHints);
                return $potentialHints[$randomIndex];
            }

            $wiggleRoom += 5;
        }

        // Hvis ingen hints fundet, find bedste ord under 300 og bedre end bedste gæt
        $bestHintWord = null;
        $bestHintRank = PHP_INT_MAX;

        foreach ($document->similar_items as $index => $item) {
            $rank = $index + 2;

            if ($rank >= 2 && $rank < min(300, $bestRank)) {
                if ($item instanceof \MongoDB\Model\BSONArray && count($item) >= 2) {
                    $word = (string)$item[0];

                    $alreadyGuessed = false;
                    foreach ($_SESSION['guesses'] as $guess) {
                        if (strtolower($guess['word']) === strtolower($word)) {
                            $alreadyGuessed = true;
                            break;
                        }
                    }

                    if (!$alreadyGuessed && $rank < $bestHintRank) {
                        $bestHintWord = $word;
                        $bestHintRank = $rank;
                    }
                }
            }
        }

        return $bestHintWord ? ['word' => $bestHintWord, 'rank' => $bestHintRank] : null;
    }

    public function giveUp()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['target_word']) || ($_SESSION['game_won'] ?? false)) {
            echo json_encode(['error' => 'Intet aktivt spil eller spil allerede vundet.']);
            return;
        }

        $targetWord = $_SESSION['target_word'];

        $_SESSION['game_given_up'] = true;
        $_SESSION['game_won'] = false;

        echo json_encode([
            'success' => true,
            'targetWord' => $targetWord,
            'redirectToGameOver' => true
        ]);
    }
}
