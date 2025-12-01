<?php
/**
 * Icon Generator Script for Crew Plan PWA
 * Generates placeholder icons in various sizes
 * Note: Requires PHP GD extension
 * 
 * Usage: Run this script once to generate placeholder icons
 * Then replace with your actual app icons
 */

// Check if GD extension is available
if (!extension_loaded('gd')) {
    die('Error: GD extension is not loaded. Please install PHP GD extension or use online tools to generate icons.');
}

// Create icons directory if it doesn't exist
$iconsDir = __DIR__ . '/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

// Icon sizes to generate
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Icon configuration
$bgColor = [22, 163, 74]; // Green (#16a34a) - RGB
$textColor = [255, 255, 255]; // White - RGB
$text = 'CP'; // Crew Plan abbreviation

foreach ($sizes as $size) {
    // Create image
    $image = imagecreatetruecolor($size, $size);
    
    // Allocate colors
    $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
    $textCol = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $size, $size, $bg);
    
    // Add text (scaled by size)
    $fontSize = max(12, $size / 4); // Font size proportional to icon size
    $font = 5; // Built-in font (1-5)
    
    // Calculate text position (centered)
    $textBox = imagestring($image, $font, 0, 0, $text, $textCol);
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2;
    
    // Draw text
    imagestring($image, $font, $x, $y, $text, $textCol);
    
    // Save icon
    $filename = $iconsDir . "/icon-{$size}x{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);
    
    echo "Generated: icon-{$size}x{$size}.png\n";
}

echo "\n✅ All icons generated successfully!\n";
echo "📝 Note: These are placeholder icons. Replace them with your actual app icons.\n";
echo "💡 Tip: Use online tools like https://www.pwabuilder.com/imageGenerator to create professional icons.\n";

