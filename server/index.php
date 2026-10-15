<?php
// CSV remains the single source of games; all launches use fpgaloader.php.
$games = [];
$catalogError = false;
$csv = @fopen(__DIR__ . '/bitstreams.csv', 'r');
if ($csv === false) {
    $catalogError = true;
} else {
    fgetcsv($csv, 0, ',', '"', ''); // Header.
    while (($row = fgetcsv($csv, 0, ',', '"', '')) !== false) {
        $title = trim($row[0] ?? '');
        $filename = trim($row[1] ?? '');
        if ($title === '' || $filename === '') continue;
        $picture = basename(trim($row[2] ?? ''));
        $games[] = [
            'title' => $title,
            'url' => 'fpgaloader.php?name=' . rawurlencode(basename($filename)),
            'image' => $picture !== '' && is_file(__DIR__ . '/images/' . $picture)
                ? 'images/' . rawurlencode($picture) : '',
            'description' => trim($row[3] ?? ''),
        ];
    }
    fclose($csv);
}
function html($value) {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$first = $games[0] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <link rel="icon" href="data:,">
    <title>A.R.C.A.D.E. — Choose a game</title>
    <link rel="stylesheet" href="menu.css">
    <script src="menu.js" defer></script>
</head>
<body>
    <div class="cabinet-stripes" aria-hidden="true"></div>
    <div class="kiosk">
        <header class="masthead">
            <div class="brand"><span class="brand-mark" aria-hidden="true"></span> USAFA <span class="brand-department">ECE</span></div>
            <span class="masthead-label">A.R.C.A.D.E.</span>
        </header>
        <main id="main">
            <div class="intro">
                <p class="eyebrow">Press play. Make history.</p>
                <h1>Choose your game<span class="accent">.</span></h1>
            </div>
            <?php if ($first !== null): ?>
            <div class="library">
                <nav class="game-library" aria-label="Choose a game">
                    <div class="library-heading"><span>The lineup</span><span><?= str_pad((string) count($games), 2, '0', STR_PAD_LEFT) ?> games</span></div>
                    <ul class="game-list" id="game-menu">
                    <?php foreach ($games as $index => $game): ?>
                        <li>
                            <a class="game-link<?= $index === 0 ? ' is-selected' : '' ?>"
                               href="<?= html($game['url']) ?>"
                               data-title="<?= html($game['title']) ?>"
                               data-image="<?= html($game['image']) ?>"
                               data-description="<?= html($game['description']) ?>"
                               tabindex="0"<?= $index === 0 ? ' aria-current="true"' : '' ?>>
                                <span class="game-number" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="game-title"><?= html($game['title']) ?></span>
                                <span class="play-arrow" aria-hidden="true">↗</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </nav>
                <aside class="game-preview" aria-label="Selected game preview">
                    <div class="artwork" id="artwork"<?= $first['image'] === '' ? ' data-empty="true"' : '' ?>>
                        <span class="artwork-placeholder" aria-hidden="true">A.R.C.A.D.E.</span>
                        <img id="game-art" src="<?= html($first['image'] ?: 'data:,') ?>" alt="<?= html($first['title']) ?> artwork"<?= $first['image'] === '' ? ' hidden' : '' ?>>
                    </div>
                    <div class="preview-caption"><span class="ready-dot" aria-hidden="true"></span><span id="selection-status" role="status"><?= html($first['title']) ?> · Ready to play</span></div>
                    <p class="game-description" id="game-description"><?= html($first['description']) ?></p>
                </aside>
            </div>
            <?php else: ?>
            <div class="empty-state" role="status">
                <h2><?= $catalogError ? 'Game library unavailable' : 'No games just yet' ?></h2>
                <p><?= $catalogError ? 'The game list could not be read. Please ask the cabinet operator for help.' : 'Add a game to the cabinet library to start playing.' ?></p>
            </div>
            <?php endif; ?>
        </main>
        <footer class="controls" aria-label="Arcade controls">
            <div class="control-hint"><span class="direction-keys" aria-hidden="true">↑ ↓ ← →</span><span>Choose a game</span></div>
            <div class="control-hint"><span class="button-key">A</span><span class="button-key">B</span><span class="text-key">Start</span><span class="text-key">Select</span><span>Play</span></div>
            <span class="footer-label">Built to play.</span>
        </footer>
        <noscript><p class="noscript-note">Use Tab to choose a game and Enter to play. Arcade navigation requires JavaScript.</p></noscript>
    </div>
    <dialog class="load-dialog" id="load-dialog" aria-labelledby="load-title" aria-describedby="load-message">
        <div class="load-symbol" id="load-symbol" aria-hidden="true"></div>
        <h2 id="load-title">Loading game</h2>
        <p id="load-message" role="status">Please wait while the FPGA is loaded.</p>
        <p class="continue-hint" id="continue-hint" hidden>Press any key to return to the menu.</p>
        <button id="continue-button" type="button" hidden>Continue <span aria-hidden="true">↗</span></button>
    </dialog>
</body>
</html>
