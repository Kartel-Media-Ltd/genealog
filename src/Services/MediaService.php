<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Uuid;
use App\Repositories\PersonRepository;

class MediaService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_DIMENSION = 800;
    private const THUMB_SIZE    = 200;

    public function __construct(private readonly PersonRepository $personRepo) {}

    /**
     * Validates, converts (GD→WebP), stores and returns the relative path.
     * @param array $uploadedFile $_FILES['photo'] element
     */
    public function uploadPhoto(string $personId, string $treeId, array $uploadedFile): string
    {
        // 1. Upload error check
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Błąd przesyłania pliku (kod: ' . $uploadedFile['error'] . ').');
        }

        // 2. Size check
        $maxBytes = (defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 10) * 1024 * 1024;
        if ($uploadedFile['size'] > $maxBytes) {
            throw new \InvalidArgumentException('Plik jest za duży (max ' . (defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 10) . ' MB).');
        }

        // 3. MIME check via finfo (not extension)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('Niedozwolony typ pliku. Akceptujemy: JPEG, PNG, WebP, GIF.');
        }

        // 4. Create target directory
        $storageDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage')
            . '/media/' . $treeId;

        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
            throw new \RuntimeException('Nie udało się utworzyć katalogu storage.');
        }

        // 5. Load image via GD
        $srcImage = $this->loadImage($uploadedFile['tmp_name'], $mimeType);

        // 6. UUID filename — używamy Uuid::generate() (RFC 4122 v4) zamiast bin2hex(random_bytes)
        $uuid = Uuid::generate();

        // 7. Full-size WebP (max 800px, maintain aspect ratio)
        $full = $this->resizeImage($srcImage, self::MAX_DIMENSION);
        $fullPath = $storageDir . '/' . $uuid . '.webp';
        imagewebp($full, $fullPath, 85);
        imagedestroy($full);

        // 8. Thumbnail 200×200 center crop
        $thumb     = $this->cropSquare($srcImage, self::THUMB_SIZE);
        $thumbPath = $storageDir . '/' . $uuid . '_thumb.webp';
        imagewebp($thumb, $thumbPath, 80);
        imagedestroy($thumb);

        imagedestroy($srcImage);

        // 9. Relative path stored in DB
        $relativePath = $treeId . '/' . $uuid . '.webp';
        $this->personRepo->updatePhotoPath($personId, $treeId, $relativePath);

        return $relativePath;
    }

    /** @return \GdImage */
    private function loadImage(string $path, string $mime): \GdImage
    {
        $image = match($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif'  => imagecreatefromgif($path),
            default      => false,
        };

        if ($image === false) {
            throw new \RuntimeException('Nie udało się przetworzyć obrazu.');
        }

        // Normalize alpha for PNG/GIF
        if (in_array($mime, ['image/png', 'image/gif'], true)) {
            $normalized = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($normalized, 0, 0, imagecolorallocate($normalized, 255, 255, 255));
            imagecopy($normalized, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            return $normalized;
        }

        return $image;
    }

    /** @return \GdImage */
    private function resizeImage(\GdImage $src, int $maxDim): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $maxDim && $h <= $maxDim) {
            // Clone the image to avoid freeing the source
            $copy = imagecreatetruecolor($w, $h);
            imagecopy($copy, $src, 0, 0, 0, 0, $w, $h);
            return $copy;
        }

        $ratio  = $w > $h ? $maxDim / $w : $maxDim / $h;
        $newW   = (int)round($w * $ratio);
        $newH   = (int)round($h * $ratio);
        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        return $resized;
    }

    /** @return \GdImage — square center crop */
    private function cropSquare(\GdImage $src, int $size): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        // Scale so smallest dimension = $size
        $ratio    = $w < $h ? $size / $w : $size / $h;
        $scaledW  = (int)round($w * $ratio);
        $scaledH  = (int)round($h * $ratio);
        $scaled   = imagecreatetruecolor($scaledW, $scaledH);
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $scaledW, $scaledH, $w, $h);

        // Crop to center
        $cropX  = (int)floor(($scaledW - $size) / 2);
        $cropY  = (int)floor(($scaledH - $size) / 2);
        $thumb  = imagecreatetruecolor($size, $size);
        imagecopy($thumb, $scaled, 0, 0, $cropX, $cropY, $size, $size);
        imagedestroy($scaled);

        return $thumb;
    }
}
