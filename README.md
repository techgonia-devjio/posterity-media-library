# Posterity Media Library

A professional, standalone Media Library for Laravel that prioritizes portability, performance, and developer experience. Designed to work with or without a database using a **Self-Contained Asset** pattern.

## 🚀 Key Features

- **Decoupled Engine:** Works independently of any UI or CMS.
- **Self-Contained Metadata:** Stores technical data, alt text, and UUIDs in `.json` sidecar files within a `.meta/` folder next to your media.
- **Extensible Processing:** Driver-based architecture. Easily add processors for Images, Videos, PDFs, etc.
- **Image Presets (YAML):** Define complex effect chains (resize, crop, blur, convert) in a UI-editable YAML file.
- **Focus Point System:** Integrated logic for selecting and using smart-crop centers.
- **Video Optimization:** Integrated FFmpeg support for automatic frame extraction and web-friendly optimization.
- **Responsive Srcset:** Fluent API to generate full `srcset` strings.
- **Flexible Caching:** Multi-disk support (S3, R2, Local) for processed thumbnails.
- **Speed Layer:** Uses Laravel's Cache system for lightning-fast UUID-to-Path lookups.

## 📁 The Sidecar Architecture

Everything stays together. Move a folder physically, and its metadata moves with it.

```text
uploads/
├── vacation.mp4
├── hero-banner.jpg
└── .meta/
    ├── hero-banner_jpg/
    │   ├── metadata.json       <-- Multi-lingual Meta, EXIF, Focus Point
    │   └── cache/              <-- Processed thumbnails
    └── vacation_mp4/
        ├── metadata.json       <-- Video duration, bitrate
        └── cache/              <-- Extracted video frames
```

## 📖 Usage

### Image Processing
```php
use Posterity\MediaLibrary\Facades\Media;

// Fluent API
<img src="{{ Media::url($asset)->width(800)->blur(5) }}">

// Named Presets
<img src="{{ Media::url($asset)->preset('hero') }}">

// Responsive Srcset
<img srcset="{{ Media::url($asset)->srcset([320, 640, 1200]) }}">
```

### Focus Point Logic
The library stores focus points as percentage coordinates (0-100) in the asset metadata. This logic is used automatically during `fit` or `cover` operations.

```php
$asset->focusX = 80; // 80% from left
$asset->focusY = 20; // 20% from top
Media::save($asset);
```

### Video Processing
```php
// Extract frame at 10 seconds as a thumbnail
<img src="{{ Media::url($videoAsset)->frame(10) }}">
```

### Metadata Extraction
Posterity automatically extracts technical data upon upload:
- **Images:** EXIF, IPTC, GPS, Dimensions, Mime.
- **Videos:** Duration, Resolution, Bitrate (via FFmpeg).

## 🏗 Modular CMS Vision
This is the foundational media module for the Posterity CMS. It is designed to be consumed by **Page Blocks** and **Filament Resources** while remaining completely decoupled from them.

## 📄 License
The MIT License (MIT).
