<?php
/**
 * 2DO Board – aspect-ratio preview page
 *
 * Shows the board image at several common in-world surface ratios.
 * Each canvas is generated at 512 × round(512 × h/w) so it fills
 * the full pixel budget without yscale distortion, then displayed
 * at its natural size (100%).
 *
 * Open at: https://localhost:8082/examples.php
 */

$ratios = [
    ['w' => 1,   'h' => 1,   'label' => '1 × 1 m (square)'],
    ['w' => 1.5, 'h' => 2,   'label' => '1.5 × 2 m'],
    ['w' => 1,   'h' => 2,   'label' => '1 × 2 m'],
    ['w' => 2,   'h' => 3,   'label' => '2 × 3 m'],
];

$base_w = 512;

function ratio_url(float $dw, float $dh, int $base_w, string $theme): string {
    $h = (int) round($base_w * $dh / $dw);
    return 'events.php?' . http_build_query([
        'format' => 'png',
        'width'  => $base_w,
        'height' => $h,
        'theme'  => $theme,
    ]);
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>2DO Board – preview</title>
  <style>
    body {
      font-family: system-ui, sans-serif;
      font-size: 13px;
      background: #ccc;
      color: #202124;
      padding: 24px;
    }
    h1 { font-size: 16px; font-weight: 500; margin-bottom: 4px; }
    h2 { font-size: 12px; font-weight: 600; text-transform: uppercase;
         letter-spacing: .06em; color: #555; margin: 32px 0 12px; }
    .boards { display: flex; flex-wrap: wrap; gap: 32px; align-items: flex-start; }
    figure { margin: 0; }
    figure img { display: block; }
    figcaption { margin-top: 6px; font-size: 12px; color: #444; }
  </style>
</head>
<body>

<h1>2DO Board – aspect-ratio preview</h1>

<h2>Light theme</h2>
<div class="boards">
<?php foreach ($ratios as $r): ?>
  <?php $url = ratio_url($r['w'], $r['h'], $base_w, 'light');
        $ch  = (int) round($base_w * $r['h'] / $r['w']); ?>
  <figure>
    <img src="<?= htmlspecialchars($url) ?>" width="<?= $base_w ?>" height="<?= $ch ?>">
    <figcaption><?= htmlspecialchars($r['label']) ?> — canvas <?= $base_w ?>×<?= $ch ?></figcaption>
  </figure>
<?php endforeach; ?>
</div>

<h2>Dark theme</h2>
<div class="boards">
<?php foreach ($ratios as $r): ?>
  <?php $url = ratio_url($r['w'], $r['h'], $base_w, 'dark');
        $ch  = (int) round($base_w * $r['h'] / $r['w']); ?>
  <figure>
    <img src="<?= htmlspecialchars($url) ?>" width="<?= $base_w ?>" height="<?= $ch ?>">
    <figcaption><?= htmlspecialchars($r['label']) ?> — canvas <?= $base_w ?>×<?= $ch ?></figcaption>
  </figure>
<?php endforeach; ?>
</div>

</body>
</html>
