<?php
declare(strict_types=1);

$dataFile = __DIR__ . '/data.json';

function textLength(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function lowerText(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

$error = null;
$analytics = null;

try {
    $json = file_get_contents($dataFile);
    if ($json === false) {
        throw new RuntimeException('Unable to read data.json.');
    }

    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $messages = $data['messages'] ?? [];
    if (!is_array($messages) || $messages === []) {
        throw new RuntimeException('No messages found in data.json.');
    }

    $botName = $data['name'] ?? 'Assistant';

    $sentCount = 0;
    $receivedCount = 0;
    $sentCharacters = 0;
    $receivedCharacters = 0;
    $wordCounts = [];

    foreach ($messages as $message) {
        if (($message['type'] ?? '') !== 'message') {
            continue;
        }

        $text = is_string($message['text'] ?? null) ? $message['text'] : '';
        $length = textLength($text);
        $isReceived = ($message['from'] ?? '') === $botName;

        if ($isReceived) {
            $receivedCount++;
            $receivedCharacters += $length;
            continue;
        }

        $sentCount++;
        $sentCharacters += $length;

        preg_match_all("/[\p{L}\p{N}']+/u", lowerText($text), $matches);
        foreach ($matches[0] as $word) {
            $word = trim($word, "'");
            if ($word !== '') {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }
    }

    uksort($wordCounts, static fn (string $a, string $b): int => [$wordCounts[$b], $a] <=> [$wordCounts[$a], $b]);
    $topWords = array_slice($wordCounts, 0, 5, true);

    $analytics = [
        'sentCount' => $sentCount,
        'receivedCount' => $receivedCount,
        'totalCount' => $sentCount + $receivedCount,
        'averageSent' => $sentCount > 0 ? $sentCharacters / $sentCount : 0.0,
        'averageReceived' => $receivedCount > 0 ? $receivedCharacters / $receivedCount : 0.0,
        'topWords' => $topWords,
        'maxWordCount' => $topWords === [] ? 0 : max($topWords),
    ];
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

function formatNumber(float $value): string
{
    return number_format($value, 1, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Message Analytics</title>
    <style>
      :root {
        color-scheme: light;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: #16211c;
        background: #f2f6f3;
      }

      * {
        box-sizing: border-box;
      }

      body {
        min-width: 320px;
        min-height: 100vh;
        margin: 0;
        padding: 32px 20px;
      }

      .dashboard {
        width: min(100%, 980px);
        margin: 0 auto;
      }

      header {
        margin-bottom: 24px;
      }

      header h1 {
        margin: 0 0 6px;
        font-size: clamp(1.5rem, 4vw, 2rem);
        letter-spacing: -0.03em;
      }

      header p {
        margin: 0;
        color: #63736b;
      }

      .error {
        padding: 18px 20px;
        border: 1px solid #f1c4c4;
        border-radius: 14px;
        background: #fdf1f1;
        color: #8d2a2a;
      }

      .metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
      }

      .metric {
        padding: 20px;
        border: 1px solid #dfe8e2;
        border-radius: 16px;
        background: #fff;
      }

      .metric__label {
        display: block;
        margin-bottom: 8px;
        color: #63736b;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
      }

      .metric__value {
        font-size: clamp(1.6rem, 3.4vw, 2.1rem);
        font-weight: 700;
        line-height: 1.1;
      }

      .metric__unit {
        margin-left: 2px;
        color: #63736b;
        font-size: 0.8rem;
        font-weight: 600;
      }

      .panel {
        padding: 24px;
        border: 1px solid #dfe8e2;
        border-radius: 16px;
        background: #fff;
      }

      .panel h2 {
        margin: 0 0 4px;
        font-size: 1.1rem;
      }

      .panel__note {
        margin: 0 0 18px;
        color: #63736b;
        font-size: 0.85rem;
      }

      .word-list {
        display: grid;
        gap: 12px;
        margin: 0;
        padding: 0;
        list-style: none;
      }

      .word-row {
        display: grid;
        grid-template-columns: 130px 1fr 40px;
        align-items: center;
        gap: 12px;
      }

      .word-row__term {
        overflow: hidden;
        font-weight: 650;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .word-row__bar {
        overflow: hidden;
        height: 10px;
        border-radius: 999px;
        background: #edf2ee;
      }

      .word-row__fill {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: #22a05a;
      }

      .word-row__count {
        color: #4d5c54;
        font-size: 0.9rem;
        text-align: right;
      }

      .empty {
        color: #63736b;
      }

      @media (max-width: 780px) {
        .metrics {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      @media (max-width: 460px) {
        body {
          padding: 20px 14px;
        }

        .metrics {
          grid-template-columns: 1fr;
        }

        .word-row {
          grid-template-columns: 90px 1fr 32px;
        }
      }
    </style>
  </head>
  <body>
    <main class="dashboard">
      <header>
        <h1>Message Analytics</h1>
        <p>
          <?php if ($analytics !== null): ?>
            Based on <?= $analytics['totalCount'] ?> messages from
            <strong><?= htmlspecialchars($data['name'] ?? 'Assistant', ENT_QUOTES, 'UTF-8') ?></strong>
            conversation data. Sent messages are from the user; received messages are from the bot.
          <?php else: ?>
            Conversation summary from <code>data.json</code>.
          <?php endif; ?>
        </p>
      </header>

      <?php if ($error !== null): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php else: ?>
        <section class="metrics" aria-label="Conversation metrics">
          <article class="metric">
            <span class="metric__label">Messages sent</span>
            <span class="metric__value"><?= $analytics['sentCount'] ?></span>
          </article>

          <article class="metric">
            <span class="metric__label">Messages received</span>
            <span class="metric__value"><?= $analytics['receivedCount'] ?></span>
          </article>

          <article class="metric">
            <span class="metric__label">Avg. sent characters</span>
            <span class="metric__value">
              <?= formatNumber($analytics['averageSent']) ?><span class="metric__unit">chars</span>
            </span>
          </article>

          <article class="metric">
            <span class="metric__label">Avg. received characters</span>
            <span class="metric__value">
              <?= formatNumber($analytics['averageReceived']) ?><span class="metric__unit">chars</span>
            </span>
          </article>
        </section>

        <section class="panel" aria-labelledby="top-words-title">
          <h2 id="top-words-title">Top 5 sent words</h2>
          <p class="panel__note">Most frequent words in messages sent by the user, case-insensitive.</p>

          <?php if ($analytics['topWords'] === []): ?>
            <p class="empty">No words available.</p>
          <?php else: ?>
            <ol class="word-list">
              <?php foreach ($analytics['topWords'] as $word => $count): ?>
                <li class="word-row">
                  <span class="word-row__term"><?= htmlspecialchars($word, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="word-row__bar">
                    <span
                      class="word-row__fill"
                      style="width: <?= $analytics['maxWordCount'] > 0 ? round(($count / $analytics['maxWordCount']) * 100, 1) : 0 ?>%"
                    ></span>
                  </span>
                  <span class="word-row__count"><?= $count ?>x</span>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </body>
</html>
