<?php

declare(strict_types=1);

$sourcePath = dirname(__DIR__).'/public/images/logo.jpeg';
$outputDirectory = dirname($sourcePath);

if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
    throw new RuntimeException('Ekstensi GD dengan dukungan WebP diperlukan.');
}

$source = imagecreatefromjpeg($sourcePath);

if ($source === false) {
    throw new RuntimeException('Logo JPEG tidak dapat dibaca.');
}

$writeSquare = static function ($sourceImage, int $size, string $path, int $quality = 84): void {
    $scaled = imagescale($sourceImage, $size, $size, IMG_BICUBIC_FIXED);

    if ($scaled === false || ! imagewebp($scaled, $path, $quality)) {
        throw new RuntimeException('Gagal membuat aset '.$path);
    }

    imagedestroy($scaled);
};

$writeSquare($source, 640, $outputDirectory.'/logo.webp', 86);
$writeSquare($source, 160, $outputDirectory.'/logo-small.webp', 84);

$favicon = imagescale($source, 64, 64, IMG_BICUBIC_FIXED);

if ($favicon === false || ! imagepng($favicon, $outputDirectory.'/favicon.png', 8)) {
    throw new RuntimeException('Gagal membuat favicon.');
}

imagedestroy($favicon);

$openGraph = imagecreatetruecolor(1200, 630);
$navy = imagecolorallocate($openGraph, 11, 25, 51);
$gold = imagecolorallocate($openGraph, 214, 168, 61);
imagefill($openGraph, 0, 0, $navy);
imageellipse($openGraph, 1060, 90, 360, 360, $gold);
$logo = imagescale($source, 420, 420, IMG_BICUBIC_FIXED);

if ($logo === false) {
    throw new RuntimeException('Gagal menyiapkan logo Open Graph.');
}

imagecopy($openGraph, $logo, 390, 105, 0, 0, 420, 420);

if (! imagewebp($openGraph, $outputDirectory.'/og-default.webp', 84)) {
    throw new RuntimeException('Gagal membuat Open Graph image.');
}

imagedestroy($logo);
imagedestroy($openGraph);
imagedestroy($source);

echo 'Brand assets generated successfully.'.PHP_EOL;
