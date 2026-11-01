<?php
/**
 * Error page shown for any HTML error. Copy it to views/errors/404.php (or 403.php, 500.php, ...)
 * to customise one status. Variables: $status, $title, $message,
 * $errors (field => [messages], validation only) and $details (debug info or null).
 * Escape everything with e().
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($status) ?> <?= e($title) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f8f8f8; color: #333; }
        .wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; }
        h1 { font-size: 2.5rem; margin: 0 0 0.5rem; }
        p.message { font-size: 1.15rem; color: #666; margin: 0.25rem 0 1rem; }
        ul.errors { text-align: left; color: #b00020; }
        a { color: #1877f2; }
        pre { text-align: left; background: #1e1e1e; color: #ddd; padding: 1rem; border-radius: 6px; max-width: 100%; overflow: auto; font-size: 0.8rem; }
        .debug { width: 100%; max-width: 900px; margin-top: 1.5rem; }
        .debug h2 { font-size: 1rem; margin: 0 0 0.5rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1><?= e($status) ?> <?= e($title) ?></h1>
        <p class="message"><?= e($message) ?></p>
        <?php if (!empty($errors)): ?>
            <ul class="errors">
                <?php foreach ($errors as $field => $messages): ?>
                    <?php foreach ((array) $messages as $text): ?>
                        <li><?= e($text) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a href="/">Go to the home page</a></p>
        <?php if (!empty($details)): ?>
            <div class="debug">
                <h2><?= e($details['exception']) ?> at <?= e($details['file']) ?>:<?= e($details['line']) ?></h2>
                <pre><?= e(implode("\n", $details['trace'])) ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
