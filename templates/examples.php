<?php
/**
 * 2DO Board – aspect-ratio preview page
 *
 * Each panel requests a 512×512 texture with a boardWidth/boardHeight ratio.
 * The PHP generates an internal canvas at the natural ratio, composes the board
 * without distortion, then resamples to 512×512. The HTML stretches each image
 * back to the board ratio so you see exactly what appears on the in-world face.
 *
 * Open at: https://localhost:8082/examples.php
 */

// ratio = width/height of the board face
$ratios = [
	["ratio" => 1 / 1, "label" => "1:1 (square)"],
	["ratio" => 1.5 / 2, "label" => "1.5:2 (portrait)"],
	["ratio" => 1 / 2, "label" => "1:2 (portrait tall)"],
	["ratio" => 2 / 3, "label" => "2:3 (portrait)"],
];

function ratio_url(float $ratio, int $texW, int $texH, string $theme): string
{
	return "events.php?" .
		http_build_query([
			"format" => "png",
			// 'textureWidth'  => $texW,
			// 'textureHeight' => $texH,
			"ratio" => round($ratio, 6),
			"theme" => $theme,
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
      background: #999;
      color: #202124;
      padding: 24px;
    }
    h1 { font-size: 16px; font-weight: 500; margin-bottom: 4px; }
    h2 { font-size: 12px; font-weight: 600; text-transform: uppercase;
         letter-spacing: .06em; color: #333; margin: 32px 0 12px; }
    .boards { display: flex; flex-wrap: wrap; gap: 32px; align-items: flex-start; }
    figure { margin: 0; }
    figure img { display: block; }
    figcaption { margin-top: 6px; font-size: 12px; color: #222; }
  </style>
</head>
<body>

<h1>2DO Board – aspect-ratio preview</h1>
<p style="color:#333;margin-bottom:4px;font-size:12px">
  All images are 512×512 px. Each is displayed at its board ratio to simulate the in-world appearance.
</p>

<?php foreach (["light", "dark"] as $theme): ?>
<h2><?= ucfirst($theme) ?> theme</h2>
<div class="boards">
<?php foreach ($ratios as $r):

	$url  = ratio_url($r["ratio"], 512, 512, $theme);
	$cssW = max(512, (int) round(512 * $r["ratio"]));
	$cssH = (int) round($cssW / $r["ratio"]);
	?>
  <figure>
    <img src="<?= htmlspecialchars($url) ?>"
         width="<?= $cssW ?>" height="<?= $cssH ?>"
         style="width:<?= $cssW ?>px;height:<?= $cssH ?>px"
         loading="lazy">
    <figcaption><?= htmlspecialchars($r["label"]) ?></figcaption>
  </figure>
<?php
endforeach; ?>
</div>
<?php endforeach; ?>

</body>
</html>
