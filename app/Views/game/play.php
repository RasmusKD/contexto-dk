<?php
// Hjælpefunktion til konsistent rank visualisering
function getVisualDataForRank($rank, $totalWordsInList = 10000) {
    $bgColor = "rgba(200, 200, 200, 0.1)";
    $barColor = "var(--gray)";
    $widthPercentage = "0%";

    if (!$rank) {
        return compact('bgColor', 'barColor', 'widthPercentage');
    }

    // Korrekt svar
    if ($rank === 1) {
        return [
            'bgColor' => "rgba(0, 186, 124, 0.1)",
            'barColor' => "var(--green)",
            'widthPercentage' => "100%"
        ];
    }

    // Beregn ikke-lineær progress procent
    $maxRank = min($totalWordsInList, 10000);
    $rankRatio = ($rank - 1) / $maxRank;
    $percentage = max(1, 100 * exp(-5 * $rankRatio));
    $widthPercentage = min(100, $percentage) . "%";

    // Farve tærskler
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

    return compact('bgColor', 'barColor', 'widthPercentage');
}

// Standardværdier for at undgå undefined variable fejl
$gameNumber = $gameNumber ?? 0;
$guessCount = $guessCount ?? 0;
$isRandomGame = $isRandomGame ?? false;
$totalWordsInList = $totalWordsInList ?? 0;
$pastGamesWithNumbers = $pastGamesWithNumbers ?? [];
?>
<!DOCTYPE html>
<html lang="da" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contexto DK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/game.css">
</head>
<body>
<div class="flex flex-col items-center py-8 px-4">
    <div class="w-full max-w-xl relative">
        <!-- Top bar med titel og indstillinger -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Contexto DK</h1>

            <div class="flex items-center">
                <button id="themeToggle" style="color: var(--text-secondary);" class="mr-2">
                    <i class="fas fa-moon text-xl" id="moon-icon"></i>
                    <i class="fas fa-sun text-xl hidden" id="sun-icon"></i>
                </button>

                <div class="settings-container relative">
                    <button style="color: var(--text-secondary);" class="p-2 focus:outline-none">
                        <i class="fas fa-cog text-xl"></i>
                    </button>
                    <div class="settings-dropdown">
                        <div class="py-1">
                            <a href="#" id="howToPlay" class="block px-4 py-2 hover:bg-opacity-10 hover:bg-gray-500">
                                <i class="fas fa-question-circle mr-2"></i> Hvordan man spiller
                            </a>
                            <a href="#" id="pastGamesDropdown" class="block px-4 py-2 hover:bg-opacity-10 hover:bg-gray-500">
                                <i class="fas fa-history mr-2"></i> Tidligere Spil
                            </a>
                            <a href="new-game" class="block px-4 py-2 hover:bg-opacity-10 hover:bg-gray-500">
                                <i class="fas fa-calendar-day mr-2"></i> Dagens Spil
                            </a>
                            <a href="random-game" class="block px-4 py-2 hover:bg-opacity-10 hover:bg-gray-500">
                                <i class="fas fa-random mr-2"></i> Tilfældigt Spil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hovedspil container -->
        <div class="card shadow-md rounded-lg overflow-hidden">
            <?php if (isset($message) && $message): ?>
                <div class="p-4 <?php echo (strpos(strtolower($message), 'nyt spil startet') !== false) ? 'bg-green-50 text-green-700 border-b border-green-200 dark:bg-green-900 dark:bg-opacity-20 dark:text-green-300 dark:border-green-800' : 'bg-red-50 text-red-700 border-b border-red-200 dark:bg-red-900 dark:bg-opacity-20 dark:text-red-300 dark:border-red-800'; ?>">
                    <p class="text-center font-medium"><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="p-6">
                <?php if ((isset($gameWon) && $gameWon) || (isset($_SESSION['game_given_up']) && $_SESSION['game_given_up'])): ?>
                    <!-- Sejr eller spil slut besked -->
                    <div class="text-center mb-8 p-6 rounded-lg">
                        <?php if (isset($gameWon) && $gameWon): ?>
                            <p class="text-4xl font-bold mb-4" style="color: var(--green);">Godt Gået!</p>
                            <p class="text-lg mb-2">Du gættede ordet #<?php echo $gameNumber; ?> på <?php echo $guessCount; ?> gæt.</p>
                        <?php else: ?>
                            <p class="text-4xl font-bold mb-4" style="color: var(--red);">Spil Slut</p>
                            <p class="text-lg mb-2">Ordet var: <span class="font-bold"><?php echo htmlspecialchars($_SESSION['target_word']); ?></span></p>
                            <p class="text-md mb-4">Du gav op efter <?php echo $guessCount; ?> gæt.</p>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['hint_used']) && $_SESSION['hint_used']): ?>
                            <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-lightbulb"></i> Hint blev brugt
                            </p>
                        <?php endif; ?>

                        <!-- Emoji visualisering -->
                        <div class="chart mt-4 mb-4">
                            <?php
                            $closeGuesses = $mediumGuesses = $farGuesses = 0;

                            if (isset($guesses) && is_array($guesses)) {
                                foreach ($guesses as $g) {
                                    if (isset($g['is_correct']) && $g['is_correct']) continue;

                                    if (isset($g['rank']) && isset($totalWordsInList) && $totalWordsInList > 0) {
                                        if ($g['rank'] <= 300) $closeGuesses++;
                                        elseif ($g['rank'] <= 3000) $mediumGuesses++;
                                        else $farGuesses++;
                                    } elseif (isset($g['not_found']) && $g['not_found']) {
                                        $farGuesses++;
                                    }
                                }
                            }
                            ?>
                            <div>🟩<?php echo str_repeat("🟩", max(0, $closeGuesses - 1)); ?> <?php echo $closeGuesses; ?></div>
                            <div>🟨<?php echo str_repeat("🟨", max(0, $mediumGuesses - 1)); ?> <?php echo $mediumGuesses; ?></div>
                            <div>🟥<?php echo str_repeat("🟥", max(0, $farGuesses - 1)); ?> <?php echo $farGuesses; ?></div>
                        </div>

                        <p class="mb-4 font-medium">Spil igen:</p>

                        <div class="flex justify-center space-x-4">
                            <a href="new-game" class="victory-btn victory-btn-primary inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white focus:outline-none" style="background-color: #3b82f6;">
                                <i class="fas fa-calendar-day mr-2"></i> Dagens Spil
                            </a>
                            <a href="#" id="showPastGames" class="victory-btn victory-btn-secondary inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium focus:outline-none" style="background-color: var(--card-bg); border-color: var(--border); color: var(--text);">
                                <i class="fas fa-history mr-2"></i> Tidligere Spil
                            </a>
                            <a href="random-game" class="victory-btn victory-btn-secondary inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium focus:outline-none" style="background-color: var(--card-bg); border-color: var(--border); color: var(--text);">
                                <i class="fas fa-random mr-2"></i> Tilfældigt
                            </a>
                        </div>

                        <div class="mt-4">
                            <button id="showTopWordsBtn" class="victory-btn victory-btn-secondary inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium focus:outline-none" style="background-color: var(--card-bg); border-color: var(--border); color: var(--text);">
                                <i class="fas fa-list-ol mr-2"></i> Se top 500 nærmeste ord
                            </button>
                        </div>

                        <div class="mt-6">
                            <a href="https://twitter.com/intent/tweet?text=Jeg%20klarede%20Contexto%20DK%20%23<?php echo $gameNumber; ?>%20p%C3%A5%20<?php echo $guessCount; ?>%20g%C3%A6t!%0A%0A🟩%20<?php echo $closeGuesses; ?>%0A🟨%20<?php echo $mediumGuesses; ?>%0A🟥%20<?php echo $farGuesses; ?>%0A%0APr%C3%B8v%20selv%20p%C3%A5%20contextodk.dk" target="_blank" class="inline-flex items-center px-4 py-2 text-sm font-medium hover:underline" style="color: #1da1f2;">
                                <i class="fab fa-twitter mr-2"></i> Del på Twitter
                            </a>
                        </div>
                    </div>
                <?php elseif (isset($targetWordForDisplay) && $targetWordForDisplay !== 'Intet spil aktivt'): ?>
                    <!-- Aktivt spil - input felt til gæt -->
                    <?php if (isset($gameNumber) && $gameNumber > 0): ?>
                        <div class="mb-4 px-4 py-2 font-bold rounded-full border" style="background-color: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2);">
                            <?php if (isset($isRandomGame) && $isRandomGame): ?>
                                Tilfældigt spil #<?php echo $gameNumber; ?>
                            <?php else: ?>
                                Dagligt Spil #<?php echo $gameNumber; ?> &nbsp;|&nbsp; Gæt: <?php echo isset($guessCount) ? $guessCount : 0; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mb-6">
                        <p class="mb-2" style="color: var(--text-secondary);">
                            Gæt det hemmelige ord! Der er <span class="font-semibold"><?php echo htmlspecialchars($totalWordsInList ?? 0); ?></span> ord på listen.
                        </p>
                        <p style="color: var(--text-secondary);">Jo tættere ordet, jo lavere nummer får det på listen.</p>
                    </div>

                    <form action="guess" method="POST" class="mb-4">
                        <div class="flex gap-3">
                            <input type="text" name="guess" placeholder="Indtast dit gæt og tryk Enter" autofocus autocomplete="off" required class="flex-grow p-3 border rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" style="border-color: var(--border); background-color: var(--card-bg); color: var(--text);">
                        </div>
                    </form>

                    <!-- Hint og giv op knapper -->
                    <div class="flex justify-end space-x-4">
                        <button id="hintButton" class="victory-btn victory-btn-secondary text-sm px-3 py-1 rounded border hover:bg-opacity-10 hover:bg-gray-500 transition" style="border-color: var(--border); color: var(--text-secondary);">
                            <i class="fas fa-lightbulb mr-1"></i> Få et hint
                        </button>
                        <button id="giveUpButton" class="victory-btn victory-btn-secondary text-sm px-3 py-1 rounded border hover:bg-opacity-10 hover:bg-gray-500 transition" style="border-color: var(--border); color: var(--text-secondary);">
                            <i class="fas fa-flag mr-1"></i> Giv op
                        </button>
                    </div>

                    <!-- Seneste gæt visning -->
                    <?php if (isset($guesses) && !empty($guesses)):
                        $latestGuess = null;
                        $latestTimestamp = 0;

                        foreach ($guesses as $g) {
                            if (isset($g['timestamp']) && $g['timestamp'] > $latestTimestamp) {
                                $latestTimestamp = $g['timestamp'];
                                $latestGuess = $g;
                            }
                        }

                        if ($latestGuess):
                            if (isset($latestGuess['not_found']) && $latestGuess['not_found']) {
                                $bgColor = "rgba(249, 24, 128, 0.1)";
                                $barColor = "var(--red)";
                                $widthPercentage = "0%";
                            } else {
                                $rank = isset($latestGuess['rank']) ? $latestGuess['rank'] : null;
                                $visualData = getVisualDataForRank($rank, $totalWordsInList);
                                extract($visualData);
                            }
                            ?>
                            <div class="mb-8 mt-2">
                                <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Seneste gæt:</p>
                                <div class="row-wrapper latest-guess-border animate-slideIn" style="background-color: <?php echo $bgColor; ?>">
                                    <div class="progress-bar" style="width: <?php echo $widthPercentage; ?>; background-color: <?php echo $barColor; ?>;"></div>
                                    <div class="row">
                                        <span class="font-medium"><?php echo htmlspecialchars($latestGuess['word']); ?></span>
                                        <span class="font-semibold">
                                            <?php
                                            if (isset($latestGuess['duplicate']) && $latestGuess['duplicate']) {
                                                echo "Allerede forsøgt!";
                                            } elseif (isset($latestGuess['not_found']) && $latestGuess['not_found']) {
                                                echo "Ikke på listen";
                                            } elseif (isset($latestGuess['is_correct']) && $latestGuess['is_correct']) {
                                                echo "Korrekt!";
                                            } elseif (isset($latestGuess['rank'])) {
                                                echo htmlspecialchars($latestGuess['rank']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Intet aktivt spil -->
                    <div class="text-center py-8">
                        <p style="color: var(--text-secondary);"><?php echo htmlspecialchars($targetWordForDisplay ?? 'Indlæser spil...'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($guesses) && !empty($guesses)): ?>
                    <!-- Liste over alle gæt med progress bars -->
                    <div class="border-t pt-6" style="border-color: var(--border);">
                        <h2 class="text-lg font-medium mb-4">Alle dine gæt:</h2>
                        <div>
                            <?php
                            // Sorter gæt før visning - korrekt svar først, derefter efter rank
                            usort($guesses, function ($a, $b) {
                                if (isset($a['is_correct']) && $a['is_correct']) return -1;
                                if (isset($b['is_correct']) && $b['is_correct']) return 1;
                                if ((!isset($a['rank']) || $a['rank'] === null) && (!isset($b['rank']) || $b['rank'] === null)) return 0;
                                if (!isset($a['rank']) || $a['rank'] === null) return 1;
                                if (!isset($b['rank']) || $b['rank'] === null) return -1;
                                return $a['rank'] <=> $b['rank'];
                            });

                            $latestTimestamp = 0;
                            foreach ($guesses as $g) {
                                if (isset($g['timestamp']) && $g['timestamp'] > $latestTimestamp) {
                                    $latestTimestamp = $g['timestamp'];
                                }
                            }

                            foreach ($guesses as $g):
                                if (isset($g['not_found']) && $g['not_found']) continue;

                                if (isset($g['not_found']) && $g['not_found']) {
                                    $bgColor = "rgba(249, 24, 128, 0.1)";
                                    $barColor = "var(--red)";
                                    $widthPercentage = "0%";
                                } else {
                                    $rank = isset($g['rank']) ? $g['rank'] : null;
                                    $visualData = getVisualDataForRank($rank, $totalWordsInList);
                                    extract($visualData);
                                }

                                $isLatestClass = (isset($g['timestamp']) && $g['timestamp'] == $latestTimestamp) ? "latest-guess-border" : "";
                                ?>
                                <div class="row-wrapper <?php echo $isLatestClass; ?>" style="background-color: <?php echo $bgColor; ?>">
                                    <div class="progress-bar" style="width: <?php echo $widthPercentage; ?>; background-color: <?php echo $barColor; ?>;"></div>
                                    <div class="row">
                                        <span class="font-medium"><?php echo htmlspecialchars($g['word']); ?></span>
                                        <span class="font-semibold">
                                        <?php
                                        if (isset($g['not_found']) && $g['not_found']) {
                                            echo "Ikke på listen";
                                        } elseif (isset($g['is_correct']) && $g['is_correct']) {
                                            echo "Korrekt!";
                                        } elseif (isset($g['rank'])) {
                                            echo htmlspecialchars($g['rank']);
                                        }
                                        ?>
                                    </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tidligere spil modal -->
        <div id="pastGamesModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="rounded-lg shadow-xl p-6 max-w-md w-full mx-4" style="background-color: var(--card-bg); border: 1px solid var(--border);">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Tidligere Spil</h2>
                    <button id="closePastGamesModal" style="color: var(--text-secondary);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <?php if (isset($pastGamesWithNumbers) && !empty($pastGamesWithNumbers)): ?>
                        <ul class="space-y-2">
                            <?php
                            krsort($pastGamesWithNumbers);
                            foreach ($pastGamesWithNumbers as $date => $info):
                                ?>
                                <li>
                                    <a href="?date=<?php echo urlencode($date); ?>" class="victory-btn victory-btn-secondary block p-3 rounded-md border hover:bg-opacity-10 hover:bg-gray-500" style="background-color: var(--card-bg); border-color: var(--border);">
                                        <?php echo htmlspecialchars($date); ?> - Spil #<?php echo $info['gameNumber']; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mt-4 pt-4 border-t" style="border-color: var(--border);">
                            <a href="random-game" class="victory-btn victory-btn-primary block w-full text-center p-3 rounded-md border text-white" style="background-color: #3b82f6; border-color: transparent;">
                                <i class="fas fa-random mr-2"></i> Spil et tilfældigt spil
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-center py-4" style="color: var(--text-secondary);">Ingen tidligere spil fundet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hvordan man spiller modal -->
        <div id="howToPlayModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="rounded-lg shadow-xl p-6 max-w-md w-full mx-4" style="background-color: var(--card-bg); border: 1px solid var(--border);">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Hvordan man spiller</h2>
                    <button id="closeHowToPlayModal" style="color: var(--text-secondary);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div>
                    <p class="mb-4">Contexto DK er et ordspil baseret på semantiske relationer mellem danske ord.</p>
                    <ol class="list-decimal pl-6 space-y-2 mb-4">
                        <li>Gæt det hemmelige ord.</li>
                        <li>For hvert gæt får du at vide, hvor tæt dit gæt er på det hemmelige ord.</li>
                        <li>Jo tættere dit gæt er på det hemmelige ord, jo lavere nummer får det på listen.</li>
                        <li>Ordene er relateret semantisk - ikke nødvendigvis ved stavning eller kategori.</li>
                    </ol>
                    <p>Farver indikerer, hvor tæt dit gæt er på det hemmelige ord:</p>
                    <ul class="space-y-2 mt-2">
                        <li class="p-2 rounded-md" style="background-color: rgba(0, 186, 124, 0.1); border: 1px solid rgba(0, 186, 124, 0.3); color: var(--green);">Grøn: Meget tæt på</li>
                        <li class="p-2 rounded-md" style="background-color: rgba(239, 125, 49, 0.1); border: 1px solid rgba(239, 125, 49, 0.3); color: var(--yellow);">Gul: Mellemtæt</li>
                        <li class="p-2 rounded-md" style="background-color: rgba(249, 24, 128, 0.1); border: 1px solid rgba(249, 24, 128, 0.3); color: var(--red);">Rød: Langt fra</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Giv op bekræftelses modal -->
        <div id="giveUpModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="rounded-lg shadow-xl p-6 max-w-md w-full mx-4" style="background-color: var(--card-bg); border: 1px solid var(--border);">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Bekræft handling</h2>
                    <button id="closeGiveUpModal" style="color: var(--text-secondary);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="mb-4">Er du sikker på, at du vil give op?</p>
                    <p>Det hemmelige ord vil blive afsløret, og du kan ikke fortsætte med det aktuelle spil.</p>
                </div>
                <div class="flex space-x-4 justify-end">
                    <button id="cancelGiveUp" class="victory-btn victory-btn-secondary px-4 py-2 rounded-md border" style="background-color: var(--card-bg); border-color: var(--border); color: var(--text);">
                        Annuller
                    </button>
                    <button id="confirmGiveUp" class="victory-btn px-4 py-2 rounded-md border text-white" style="background-color: var(--red); border-color: transparent;">
                        Ja, giv op
                    </button>
                </div>
            </div>
        </div>

        <!-- Top ord modal -->
        <div id="topWordsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="rounded-lg shadow-xl p-4 sm:p-6 max-w-3xl w-full mx-2 sm:mx-4 max-h-[90vh] flex flex-col" style="background-color: var(--card-bg); border: 1px solid var(--border);">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-lg sm:text-xl font-bold">
                        Top 500 nærmeste ord til "<?php echo isset($_SESSION['target_word']) ? htmlspecialchars($_SESSION['target_word']) : ''; ?>"
                    </h2>
                    <button id="closeTopWordsModal" class="p-2" style="color: var(--text-secondary);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <p class="text-xs sm:text-sm mb-4" style="color: var(--text-secondary);">
                    Bemærk: Ord-nærhed er beregnet via computermodeller og afspejler ikke nødvendigvis menneskelig intuition. Rangeringen kan indeholde overraskende forbindelser eller uventede resultater.
                </p>

                <div class="overflow-y-auto flex-grow">
                    <div id="topWordsList" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        <p class="col-span-full text-center py-4" style="color: var(--text-secondary);">Indlæser...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tema håndtering
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('theme');
        const moonIcon = document.getElementById('moon-icon');
        const sunIcon = document.getElementById('sun-icon');

        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
            moonIcon.classList.add('hidden');
            sunIcon.classList.remove('hidden');
        }

        document.getElementById('themeToggle').addEventListener('click', function() {
            const isDark = document.documentElement.classList.toggle('dark');
            moonIcon.classList.toggle('hidden', isDark);
            sunIcon.classList.toggle('hidden', !isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Modal elementer
        const pastGamesModal = document.getElementById('pastGamesModal');
        const howToPlayModal = document.getElementById('howToPlayModal');
        const topWordsModal = document.getElementById('topWordsModal');
        const giveUpModal = document.getElementById('giveUpModal');

        // Åbn modaler
        document.getElementById('pastGamesDropdown').addEventListener('click', function(e) {
            e.preventDefault();
            pastGamesModal.classList.remove('hidden');
        });

        document.getElementById('howToPlay').addEventListener('click', function(e) {
            e.preventDefault();
            howToPlayModal.classList.remove('hidden');
        });

        const showPastGames = document.getElementById('showPastGames');
        if (showPastGames) {
            showPastGames.addEventListener('click', function(e) {
                e.preventDefault();
                pastGamesModal.classList.remove('hidden');
            });
        }

        // Vis top ord knap
        const showTopWordsBtn = document.getElementById('showTopWordsBtn');
        if (showTopWordsBtn) {
            showTopWordsBtn.addEventListener('click', function() {
                topWordsModal.classList.remove('hidden');
                const topWordsList = document.getElementById('topWordsList');

                if (topWordsList && topWordsList.innerHTML.includes('Indlæser')) {
                    const targetWord = '<?php echo isset($_SESSION["target_word"]) ? htmlspecialchars($_SESSION["target_word"]) : ""; ?>';
                    fetchTopWords(targetWord);
                }
            });
        }

        // Luk modaler
        document.getElementById('closePastGamesModal').addEventListener('click', () => pastGamesModal.classList.add('hidden'));
        document.getElementById('closeHowToPlayModal').addEventListener('click', () => howToPlayModal.classList.add('hidden'));

        if (document.getElementById('closeTopWordsModal')) {
            document.getElementById('closeTopWordsModal').addEventListener('click', () => topWordsModal.classList.add('hidden'));
        }

        // Giv op funktionalitet
        const giveUpButton = document.getElementById('giveUpButton');
        if (giveUpButton) {
            giveUpButton.addEventListener('click', () => giveUpModal.classList.remove('hidden'));
        }

        if (document.getElementById('closeGiveUpModal')) {
            document.getElementById('closeGiveUpModal').addEventListener('click', () => giveUpModal.classList.add('hidden'));
        }

        if (document.getElementById('cancelGiveUp')) {
            document.getElementById('cancelGiveUp').addEventListener('click', () => giveUpModal.classList.add('hidden'));
        }

        if (document.getElementById('confirmGiveUp')) {
            document.getElementById('confirmGiveUp').addEventListener('click', function() {
                this.disabled = true;
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Afslutter...';

                fetch('api/give-up', {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                        giveUpModal.classList.add('hidden');

                        if (data.error) {
                            showMessage(data.error, 'error');
                            return;
                        }

                        if (data.success && data.redirectToGameOver) {
                            window.location.reload();
                        }
                    })
                    .catch(error => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                        giveUpModal.classList.add('hidden');
                        showMessage('Der opstod en fejl. Prøv igen.', 'error');
                        console.error('Error:', error);
                    });
            });
        }

        // Luk modaler ved klik udenfor
        window.addEventListener('click', function(e) {
            if (e.target === pastGamesModal) pastGamesModal.classList.add('hidden');
            if (e.target === howToPlayModal) howToPlayModal.classList.add('hidden');
            if (e.target === topWordsModal) topWordsModal.classList.add('hidden');
            if (e.target === giveUpModal) giveUpModal.classList.add('hidden');
        });

        // Fokuser på input felt
        const guessInput = document.querySelector('input[name="guess"]');
        if (guessInput) guessInput.focus();

        // Hent top ord funktion
        async function fetchTopWords(targetWord) {
            const topWordsList = document.getElementById('topWordsList');

            try {
                const response = await fetch(`api/top-words?word=${encodeURIComponent(targetWord)}`);
                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                if (data.error) throw new Error(data.error);

                displayTopWords(data.words || []);
            } catch (error) {
                topWordsList.innerHTML = `<p class="col-span-full text-center text-red-500">Der opstod en fejl: ${error.message}</p>`;
                console.error('Error fetching top words:', error);
            }
        }

        // Vis top ord funktion
        function displayTopWords(words) {
            const topWordsList = document.getElementById('topWordsList');

            if (!words || !words.length) {
                topWordsList.innerHTML = '<p class="col-span-full text-center py-4">Ingen ord fundet.</p>';
                return;
            }

            let html = '';
            words.forEach(item => {
                html += `
                    <div class="p-2 border rounded text-sm sm:text-base overflow-hidden" style="border-color: var(--border);">
                        <span class="font-semibold">${item.rank}.</span> ${item.word}
                    </div>
                `;
            });

            topWordsList.innerHTML = html;
        }

        // Form submission med AJAX
        const guessForm = document.querySelector('form[action="guess"]');
        if (guessForm && guessInput) {
            guessForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const guess = guessInput.value.trim();
                if (!guess) return;

                guessInput.disabled = true;
                const originalPlaceholder = guessInput.placeholder;
                guessInput.placeholder = "Behandler gæt...";

                fetch('guess', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ guess: guess })
                })
                    .then(response => response.json())
                    .then(data => {
                        guessInput.disabled = false;
                        guessInput.placeholder = originalPlaceholder;
                        guessInput.value = '';
                        guessInput.focus();

                        if (data.error) {
                            showMessage(data.error, 'error');
                            return;
                        }

                        updateGameUI(data);

                        if (data.gameWon) {
                            setTimeout(() => window.location.reload(), 1000);
                        }
                    })
                    .catch(error => {
                        guessInput.disabled = false;
                        guessInput.placeholder = originalPlaceholder;
                        showMessage('Der opstod en fejl. Prøv igen.', 'error');
                        console.error('Error:', error);
                    });
            });
        }

        // Hint funktionalitet
        const hintButton = document.getElementById('hintButton');
        if (hintButton) {
            hintButton.addEventListener('click', function() {
                hintButton.disabled = true;
                const originalText = hintButton.innerHTML;
                hintButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finder hint...';

                fetch('api/hint', {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        hintButton.disabled = false;
                        hintButton.innerHTML = originalText;

                        if (data.error) {
                            showMessage(data.error, 'error');
                            return;
                        }

                        if (data.success) {
                            updateGameUI(data);
                            if (data.gameWon) {
                                setTimeout(() => window.location.reload(), 1000);
                            }
                        }
                    })
                    .catch(error => {
                        hintButton.disabled = false;
                        hintButton.innerHTML = originalText;
                        showMessage('Der opstod en fejl ved hentning af hint.', 'error');
                        console.error('Error:', error);
                    });
            });
        }

        // Vis besked funktion
        function showMessage(message, type = 'info', duration = 4000) {
            let messageContainer = document.querySelector('.message-container');
            if (!messageContainer) {
                messageContainer = document.createElement('div');
                messageContainer.className = 'message-container p-4 mb-4 rounded-md transition-opacity';
                const formElement = document.querySelector('form[action="guess"]');
                formElement.parentNode.insertBefore(messageContainer, formElement);
            }

            messageContainer.textContent = message;
            messageContainer.style.opacity = '1';

            if (type === 'error') {
                messageContainer.style.backgroundColor = 'rgba(249, 24, 128, 0.1)';
                messageContainer.style.color = 'var(--red)';
                messageContainer.style.border = '1px solid rgba(249, 24, 128, 0.3)';
            } else {
                messageContainer.style.backgroundColor = 'rgba(0, 186, 124, 0.1)';
                messageContainer.style.color = 'var(--green)';
                messageContainer.style.border = '1px solid rgba(0, 186, 124, 0.3)';
            }

            setTimeout(() => {
                messageContainer.style.opacity = '0';
                setTimeout(() => messageContainer.remove(), 300);
            }, duration);
        }

        // Opdater spil UI funktion
        function updateGameUI(data) {
            if (data.message && data.messageType !== 'error') {
                showMessage(data.message, data.messageType || 'info');
            }

            updateLatestGuess(data.latestGuess);
            updateAllGuesses(data.allGuesses);

            if (data.guessCount !== undefined) {
                updateGuessCount(data.guessCount);
            }
        }

        // Opdater gæt tæller
        function updateGuessCount(count) {
            const guessCountElement = document.querySelector('.px-4.py-2.rounded-full.border');
            if (guessCountElement) {
                const countParts = guessCountElement.innerHTML.split('&nbsp;|&nbsp;');
                if (countParts.length > 1) {
                    countParts[1] = 'Gæt: ' + count;
                    guessCountElement.innerHTML = countParts.join('&nbsp;|&nbsp;');
                }
            }
        }

        // Opdater seneste gæt
        function updateLatestGuess(guessData) {
            if (!guessData) return;

            let latestGuessSection = document.querySelector('.mb-8.mt-2');
            if (!latestGuessSection) {
                latestGuessSection = document.createElement('div');
                latestGuessSection.className = 'mb-8 mt-2';

                // Find the hint/give up buttons section and insert after it
                const buttonsSection = document.querySelector('.flex.justify-end.mb-6.space-x-4');
                if (buttonsSection) {
                    buttonsSection.parentNode.insertBefore(latestGuessSection, buttonsSection.nextSibling);
                } else {
                    // Fallback: insert after form if buttons section not found
                    const formElement = document.querySelector('form[action="guess"]');
                    formElement.parentNode.insertBefore(latestGuessSection, formElement.nextSibling);
                }
            }

            latestGuessSection.innerHTML = `
        <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Seneste gæt:</p>
        <div class="row-wrapper latest-guess-border animate-slideIn" style="background-color: ${guessData.bgColor}">
            <div class="progress-bar" style="width: ${guessData.widthPercentage}; background-color: ${guessData.barColor};"></div>
            <div class="row">
                <span class="font-medium">${guessData.word}</span>
                <span class="font-semibold">${getLatestGuessStatusText(guessData)}</span>
            </div>
        </div>
    `;
        }

        // Opdater alle gæt
        function updateAllGuesses(guesses) {
            if (!guesses || guesses.length === 0) return;

            const displayableGuesses = guesses.filter(g => g.showInAllGuesses !== false);
            if (displayableGuesses.length === 0) return;

            let allGuessesSection = document.querySelector('.border-t.pt-6');
            if (!allGuessesSection) {
                allGuessesSection = document.createElement('div');
                allGuessesSection.className = 'border-t pt-6';
                allGuessesSection.style.borderColor = 'var(--border)';
                const cardBody = document.querySelector('.card .p-6');
                cardBody.appendChild(allGuessesSection);
            }

            allGuessesSection.innerHTML = `
                <h2 class="text-lg font-medium mb-4">Alle dine gæt:</h2>
                <div>
                    ${displayableGuesses.map(guess => `
                        <div class="row-wrapper ${guess.isLatest ? 'latest-guess-border' : ''}" style="background-color: ${guess.bgColor}">
                            <div class="progress-bar" style="width: ${guess.widthPercentage}; background-color: ${guess.barColor};"></div>
                            <div class="row">
                                <span class="font-medium">${guess.word}</span>
                                <span class="font-semibold">${getAllGuessesStatusText(guess)}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // Hjælpefunktioner til status tekst
        function getLatestGuessStatusText(guess) {
            if (guess.is_hint) return guess.rank;
            if (guess.duplicate) return "Allerede forsøgt!";
            if (guess.not_found) return "Ikke på listen";
            if (guess.is_correct) return "Korrekt!";
            if (guess.rank) return guess.rank;
            return "";
        }

        function getAllGuessesStatusText(guess) {
            if (guess.is_correct) return "Korrekt!";
            if (guess.is_hint) return guess.rank;
            if (guess.rank) return guess.rank;
            return "";
        }
    });
</script>

</body>
</html>
