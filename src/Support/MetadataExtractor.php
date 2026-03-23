<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Support;

use Posterity\MediaLibrary\Core\Contracts\StorageAdapter;
use Posterity\MediaLibrary\Core\MediaType;

class MetadataExtractor
{
    public function extract(StorageAdapter $storage, string $path): array
    {
        $mime = $storage->mimeType($path);
        $type = MediaType::fromMime($mime);

        return match ($type) {
            MediaType::Image    => $this->extractImageMetadata($storage, $path, $mime),
            MediaType::Video    => $this->extractVideoMetadata($storage, $path),
            MediaType::Document => $this->extractDocumentMetadata($mime),
            MediaType::Audio    => $this->extractAudioMetadata($storage, $path),
            default             => [],
        };
    }

    private function extractImageMetadata(StorageAdapter $storage, string $path, string $mime): array
    {
        $meta = [];

        $data = $storage->get($path);
        if ($data !== null) {
            $size = @getimagesizefromstring($data);
            if ($size !== false) {
                $meta['width']  = $size[0];
                $meta['height'] = $size[1];
            }
        }

        if (in_array($mime, ['image/jpeg', 'image/tiff'])) {
            try {
                $localPath = $storage->path($path);
                $exif      = @exif_read_data($localPath);
                if ($exif !== false) {
                    $meta = array_merge($meta, $this->parseExif($exif));
                }
            } catch (\Throwable) {}
        }

        return $meta;
    }

    private function parseExif(array $exif): array
    {
        return array_filter([
            'camera'       => $exif['Model'] ?? null,
            'make'         => $exif['Make'] ?? null,
            'aperture'     => $exif['COMPUTED']['ApertureFNumber'] ?? null,
            'iso'          => $exif['ISOSpeedRatings'] ?? null,
            'exposure'     => $exif['ExposureTime'] ?? null,
            'focal_length' => $exif['FocalLength'] ?? null,
            'software'     => $exif['Software'] ?? null,
            'created_at'   => $exif['DateTimeOriginal'] ?? null,
            'gps'          => $this->parseGps($exif),
        ], fn($v) => $v !== null);
    }

    private function parseGps(array $exif): ?array
    {
        if (! isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
            return null;
        }

        return [
            'lat' => $this->gpsToDecimal($exif['GPSLatitude'],  $exif['GPSLatitudeRef']  ?? 'N'),
            'lng' => $this->gpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E'),
        ];
    }

    private function gpsToDecimal(array $coordinate, string $ref): float
    {
        $degrees = $this->gpsRationalToFloat($coordinate[0] ?? '0');
        $minutes = $this->gpsRationalToFloat($coordinate[1] ?? '0');
        $seconds = $this->gpsRationalToFloat($coordinate[2] ?? '0');

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        return in_array($ref, ['S', 'W']) ? -$decimal : $decimal;
    }

    private function gpsRationalToFloat(string $rational): float
    {
        $parts = explode('/', $rational);

        if (count($parts) === 1) {
            return (float) $parts[0];
        }

        return (float) $parts[1] === 0.0 ? 0.0 : (float) $parts[0] / (float) $parts[1];
    }

    private function extractVideoMetadata(StorageAdapter $storage, string $path): array
    {
        if (class_exists(\getID3::class)) {
            try {
                $localPath = $storage->path($path);
                $getid3    = new \getID3();
                $info      = $getid3->analyze($localPath);

                return array_filter([
                    'duration' => $info['playtime_seconds'] ?? null,
                    'width'    => $info['video']['resolution_x'] ?? null,
                    'height'   => $info['video']['resolution_y'] ?? null,
                    'codec'    => $info['video']['codec'] ?? null,
                    'fps'      => $info['video']['frame_rate'] ?? null,
                    'bitrate'  => $info['bitrate'] ?? null,
                ], fn($v) => $v !== null);
            } catch (\Throwable) {}
        }

        return [];
    }

    private function extractDocumentMetadata(string $mime): array
    {
        return ['mime_type' => $mime];
    }

    private function extractAudioMetadata(StorageAdapter $storage, string $path): array
    {
        if (class_exists(\getID3::class)) {
            try {
                $localPath = $storage->path($path);
                $getid3    = new \getID3();
                $info      = $getid3->analyze($localPath);

                return array_filter([
                    'duration' => $info['playtime_seconds'] ?? null,
                    'bitrate'  => $info['bitrate'] ?? null,
                    'artist'   => $info['tags']['id3v2']['artist'][0] ?? $info['tags']['id3v1']['artist'][0] ?? null,
                    'album'    => $info['tags']['id3v2']['album'][0]  ?? $info['tags']['id3v1']['album'][0]  ?? null,
                    'title'    => $info['tags']['id3v2']['title'][0]  ?? $info['tags']['id3v1']['title'][0]  ?? null,
                ], fn($v) => $v !== null);
            } catch (\Throwable) {}
        }

        return [];
    }
}
