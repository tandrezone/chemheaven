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
    // 1. Setup the image resource
    $info = getimagesize($bgPath);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg': $image = imagecreatefromjpeg($bgPath); $mime = 'image/jpeg'; break;
        case 'image/png':  $image = imagecreatefrompng($bgPath);  $mime = 'image/png';  break;
        case 'image/webp': $image = imagecreatefromwebp($bgPath); $mime = 'image/webp'; break;
        default: return false;
    }

    // 2. Add Text (Simplified centering logic)
    $white = imagecolorallocate($image, 255, 255, 255);
    $box = imagettfbbox($fontSize, 0, $fontPath, $text);
    $x = (imagesx($image) - abs($box[2] - $box[0])) / 2;
    $y = (imagesy($image) + abs($box[7] - $box[1])) / 2;
    imagettftext($image, $fontSize, 0, $x, $y, $white, $fontPath, $text);

    // 3. Capture the output using a Buffer
    ob_start(); 
    imagejpeg($image, null, 90); // Output to buffer
    $imageData = ob_get_contents(); // Get the binary data
    ob_end_clean(); // Stop and clear buffer

    // 4. Cleanup and Return
    imagedestroy($image);
    
    // Return formatted as a Data URI
    return 'data:' . $mime . ';base64,' . base64_encode($imageData);
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