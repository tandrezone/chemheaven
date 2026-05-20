<?php
namespace Tandrezone\Chemheaven\Services;
class ImageManipulator
{
    public function __construct()
    {
        // Constructor code here
    }

    /**
 * Generates an image and returns it as a Base64 string.
 */
public static function createTextImageBase64($text, $bgPath, $fontPath, $fontSize = 40) {
    $info = getimagesize($bgPath);
    $width = $info[0] ?? 1200;
    $height = $info[1] ?? 900;
switch ($info['mime']) {
        case 'image/jpeg': $image = imagecreatefromjpeg($bgPath); $mime = 'image/jpeg'; break;
        case 'image/png':  $image = imagecreatefrompng($bgPath);  $mime = 'image/png';  break;
        case 'image/webp': $image = imagecreatefromwebp($bgPath); $mime = 'image/webp'; break;
        default: return false;
    }

    if (!$image) {
        return false;
    }

    imageantialias($image, true);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    self::paintTextPanel($image, $width, $height);
    self::drawCenteredText($image, $text, $fontPath, $width, $height, $fontSize);

    ob_start();
    imagejpeg($image, null, 92);
    $imageData = ob_get_contents();
    ob_end_clean();

    imagedestroy($image);

    return 'data:image/jpeg;base64,' . base64_encode($imageData);
}

    private static function paintTextPanel($image, int $width, int $height): void
    {
        $panelLeft = (int) ($width * 0.12);
        $panelTop = (int) ($height * 0.31);
        $panelRight = (int) ($width * 0.88);
        $panelBottom = (int) ($height * 0.69);
        $radius = (int) ($width * 0.03);

        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 92);
        $panelFill = imagecolorallocatealpha($image, 6, 12, 18, 34);
        $panelStroke = imagecolorallocatealpha($image, 115, 222, 232, 88);
        $panelHighlight = imagecolorallocatealpha($image, 255, 255, 255, 118);

        self::drawRoundedRectangle($image, $panelLeft + 10, $panelTop + 12, $panelRight + 10, $panelBottom + 12, $radius, $shadow, true);
        self::drawRoundedRectangle($image, $panelLeft, $panelTop, $panelRight, $panelBottom, $radius, $panelFill, true);
        self::drawRoundedRectangle($image, $panelLeft, $panelTop, $panelRight, $panelBottom, $radius, $panelStroke, false);
        imageline($image, $panelLeft + $radius, $panelTop + 1, $panelRight - $radius, $panelTop + 1, $panelHighlight);
    }

    private static function drawCenteredText($image, string $text, string $fontPath, int $width, int $height, int $fontSize): void
    {
        $maxTextWidth = (int) ($width * 0.62);
        $normalized = trim(preg_replace('/\s+/', ' ', $text));
        $adaptiveSize = $fontSize;

        if (mb_strlen($normalized) > 24) {
            $adaptiveSize = 34;
        }

        if (mb_strlen($normalized) > 34) {
            $adaptiveSize = 30;
        }

        $lines = self::wrapText($normalized, $fontPath, $adaptiveSize, $maxTextWidth);
        $lineHeight = (int) ($adaptiveSize * 1.45);
        $blockHeight = count($lines) * $lineHeight;
        $startY = (int) (($height - $blockHeight) / 2) + $adaptiveSize;

        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 50);
        $textColor = imagecolorallocatealpha($image, 245, 250, 252, 0);
        $accentColor = imagecolorallocatealpha($image, 153, 235, 231, 62);

        foreach ($lines as $index => $line) {
            $box = imagettfbbox($adaptiveSize, 0, $fontPath, $line);
            $lineWidth = abs($box[2] - $box[0]);
            $x = (int) (($width - $lineWidth) / 2);
            $y = $startY + ($index * $lineHeight);

            imagettftext($image, $adaptiveSize, 0, $x + 1, $y + 2, $shadowColor, $fontPath, $line);
            imagettftext($image, $adaptiveSize, 0, $x, $y, $textColor, $fontPath, $line);

            if ($index === count($lines) - 1) {
                imageline($image, $x, $y + 12, $x + $lineWidth, $y + 12, $accentColor);
            }
        }
    }

    private static function wrapText(string $text, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
            $candidateWidth = abs($box[2] - $box[0]);

            if ($candidateWidth <= $maxWidth || $currentLine === '') {
                $currentLine = $candidate;
                continue;
            }

            $lines[] = $currentLine;
            $currentLine = $word;
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return array_slice($lines, 0, 3);
    }

    private static function fillVerticalGradient($image, int $width, int $height, array $topColor, array $bottomColor): void
    {
        for ($y = 0; $y < $height; $y++) {
            $ratio = $height > 1 ? $y / ($height - 1) : 0;
            $red = (int) round($topColor[0] + (($bottomColor[0] - $topColor[0]) * $ratio));
            $green = (int) round($topColor[1] + (($bottomColor[1] - $topColor[1]) * $ratio));
            $blue = (int) round($topColor[2] + (($bottomColor[2] - $topColor[2]) * $ratio));
            $color = imagecolorallocate($image, $red, $green, $blue);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }

    private static function paintGlow($image, int $centerX, int $centerY, int $width, int $height, array $rgb, int $startAlpha): void
    {
        $steps = 12;
        for ($step = 0; $step < $steps; $step++) {
            $ratio = $step / max(1, $steps - 1);
            $ellipseWidth = (int) round($width * (1 - ($ratio * 0.72)));
            $ellipseHeight = (int) round($height * (1 - ($ratio * 0.72)));
            $alpha = min(127, (int) round($startAlpha + ($ratio * (127 - $startAlpha))));
            $color = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], $alpha);
            imagefilledellipse($image, $centerX, $centerY, max(1, $ellipseWidth), max(1, $ellipseHeight), $color);
        }
    }

    private static function drawRoundedRectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color, bool $filled): void
    {
        if ($filled) {
            imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
            imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
            imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
            return;
        }

        imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
        imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
    }

    public function resizeImage($sourcePath, $destinationPath, $newWidth, $newHeight)
    {
        // Code to resize image
    }

    public function cropImage($sourcePath, $destinationPath, $cropWidth, $cropHeight)
    {
        // Code to crop image
    }

    public function applyFilter($sourcePath, $destinationPath, $filterType)
    {
        // Code to apply filter to image
    }
}