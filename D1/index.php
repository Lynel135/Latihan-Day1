<?php
$availableLanguages = [
    'en_EN' => 'English',
    'id_ID' => 'Indonesia',
    'nl_NL' => 'Dutch',
];

$requestedLanguage = $_GET['lang'] ?? 'en_EN';
$language = array_key_exists($requestedLanguage, $availableLanguages)
    ? $requestedLanguage
    : 'en_EN';
$translations = json_decode(
    file_get_contents(__DIR__ . '/lang/' . $language . '.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$translate = static function (string $key) use (&$translations): string {
    return htmlspecialchars($translations[$key] ?? $key, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($language, 0, 2), ENT_QUOTES, 'UTF-8') ?>">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $translate('title') ?></title>
    <link rel="stylesheet" href="style.css" />
  </head>
  <body>
    <nav class="navbar">
      <div class="container">
        <div class="logo">
          <h1>I18N</h1>
        </div>
        <ul>
          <li><a href="#"><?= $translate('home') ?></a></li>
          <li><a href="#"><?= $translate('about_us') ?></a></li>
          <li><a href="#"><?= $translate('contact_us') ?></a></li>
          <li><a href="#"><?= $translate('register') ?></a></li>
          <li><a href="#"><?= $translate('login') ?></a></li>
          <li class="language-form">
            <label class="visually-hidden" for="lang">Language</label>
            <select name="lang" id="lang" aria-label="Language">
              <?php foreach ($availableLanguages as $code => $name): ?>
                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $language === $code ? 'selected' : '' ?>>
                  <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </li>
        </ul>
      </div>
    </nav>

    <article class="article-section">
      <div class="container">
        <h1 class="article-title"><?= $translate('title') ?></h1>
        <div class="article-content">
          <p><?= $translate('content_1') ?></p>
          <p class="article-image">
            <img src="img/images.png" alt="Multilingual website illustration" width="450" />
          </p>
          <p><?= $translate('content_2') ?></p>
        </div>
      </div>
    </article>

    <footer>
      <div class="container">
        <div class="copyright"><?= $translate('copyright') ?> &copy; 2026</div>
      </div>
    </footer>

    <script>
      document.getElementById('lang').addEventListener('change', (event) => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', event.target.value);
        window.location.assign(url);
      });
    </script>
  </body>
</html>
