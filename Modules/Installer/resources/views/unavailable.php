<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Forkiva · Installation help</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 20px; min-height: 100vh; display: grid; place-items: center; background: #f6f7f9; color: #253041; font: 16px/1.6 system-ui, sans-serif; }
        main { width: 100%; max-width: 640px; padding: clamp(24px, 5vw, 40px); border: 1px solid #e4e7ec; border-radius: 18px; background: white; }
        .brand { color: #ad4800; font-weight: 750; letter-spacing: .02em; }
        h1 { margin: 20px 0 12px; font-size: clamp(24px, 4vw, 30px); line-height: 1.25; letter-spacing: -.025em; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        p { margin: 0 0 16px; }
        ol { padding-left: 22px; margin: 0 0 24px; }
        li { padding-left: 4px; margin-bottom: 12px; }
        .reference { padding: 12px; background: #f6f7f9; border-radius: 8px; overflow-wrap: anywhere; }
        a { display: inline-block; padding: 10px 20px; border-radius: 8px; background: #ff8000; color: #202937; font-weight: 650; text-decoration: none; }
        a:focus-visible { outline: 3px solid #253041; outline-offset: 3px; }
    </style>
</head>
<body>
<main aria-labelledby="title">
    <div class="brand">Forkiva / Installation</div>
    <h1 id="title"><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <h2>What to do next</h2>
    <ol>
        <?php foreach ($steps as $step) { ?>
            <li><?= e($step) ?></li>
        <?php } ?>
    </ol>
    <?php if ($reference) { ?>
        <p class="reference">Support reference: <strong><?= e($reference) ?></strong></p>
    <?php } ?>
    <a href="<?= e(route('installer.welcome', [], false)) ?>">Reload setup</a>
</main>
</body>
</html>
