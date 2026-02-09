<?php
/**
 * Generador de iconos PNG para la PWA
 * Genera iconos en diferentes tamaños usando GD
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$outputDir = __DIR__;

foreach ($sizes as $size) {
    $image = imagecreatetruecolor($size, $size);

    // Enable alpha blending
    imagealphablending($image, true);
    imagesavealpha($image, true);

    // Background gradient colors
    $color1 = imagecolorallocate($image, 79, 172, 254);  // #4facfe
    $color2 = imagecolorallocate($image, 0, 242, 254);   // #00f2fe
    $white = imagecolorallocate($image, 255, 255, 255);

    // Create gradient background
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (int) (79 + (0 - 79) * $ratio);
        $g = (int) (172 + (242 - 172) * $ratio);
        $b = (int) (254 + (254 - 254) * $ratio);
        $lineColor = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $size, $y, $lineColor);
    }

    // Draw rounded corners (simple mask)
    $radius = (int) ($size * 0.15);

    // Draw box icon in center
    $margin = (int) ($size * 0.2);
    $boxSize = $size - ($margin * 2);

    // Main box
    $boxX = $margin;
    $boxY = $margin + (int) ($boxSize * 0.2);
    $boxW = $boxSize;
    $boxH = (int) ($boxSize * 0.7);

    imagesetthickness($image, max(2, (int) ($size * 0.03)));
    imagerectangle($image, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $white);

    // Tab on top (like a clipboard)
    $tabW = (int) ($boxSize * 0.5);
    $tabH = (int) ($boxSize * 0.25);
    $tabX = $margin + (int) (($boxSize - $tabW) / 2);
    $tabY = $margin;
    imagerectangle($image, $tabX, $tabY, $tabX + $tabW, $tabY + $tabH, $white);

    // Lines inside box (representing list items)
    $lineY = $boxY + (int) ($boxH * 0.3);
    $lineMargin = (int) ($size * 0.08);
    $lineH = max(2, (int) ($size * 0.04));

    imagefilledrectangle($image, $boxX + $lineMargin, $lineY, $boxX + $boxW - $lineMargin, $lineY + $lineH, $white);
    $lineY += (int) ($boxH * 0.2);
    imagefilledrectangle($image, $boxX + $lineMargin, $lineY, $boxX + $boxW - $lineMargin - (int) ($boxSize * 0.15), $lineY + $lineH, $white);
    $lineY += (int) ($boxH * 0.2);
    imagefilledrectangle($image, $boxX + $lineMargin, $lineY, $boxX + $boxW - $lineMargin - (int) ($boxSize * 0.08), $lineY + $lineH, $white);

    // Save
    $filename = $outputDir . "/icon-{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);

    echo "Created: icon-{$size}.png\n";
}

echo "\nAll icons generated successfully!\n";
