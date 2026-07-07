<?php
declare(strict_types=1);

/**
 * Foto-varianten voor busreizen (hero 1600px, card 800px).
 */

function reis_media_doc_root(): string
{
    return rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
}

function reis_media_variant_path(string $fotoPad, string $suffix): string
{
    $path = ltrim($fotoPad, '/');

    return preg_replace('/(\.[a-z0-9]+)$/i', $suffix . '$1', $path) ?? $path;
}

function reis_media_public_url(string $webPath): string
{
    return '/' . ltrim($webPath, '/');
}

function reis_media_ensure_variants(string $fotoPad): void
{
    $fotoPad = trim($fotoPad);
    if ($fotoPad === '') {
        return;
    }

    $docRoot = reis_media_doc_root();
    if ($docRoot === '') {
        return;
    }

    $full = $docRoot . '/' . ltrim($fotoPad, '/');
    if (!is_file($full)) {
        return;
    }

    $hero = $docRoot . '/' . reis_media_variant_path(ltrim($fotoPad, '/'), '_hero');
    if (!is_file($hero)) {
        reis_media_generate_variants($full);
    }
}

/** @return list<string> hero, card, full — eerste bestaande pad op schijf */
function reis_media_resolve(string $fotoPad, string $usage = 'hero'): array
{
    $fotoPad = trim($fotoPad);
    if ($fotoPad === '') {
        return ['src' => '', 'srcset' => ''];
    }

    reis_media_ensure_variants($fotoPad);

    $docRoot = reis_media_doc_root();
    $full = ltrim($fotoPad, '/');
    $candidates = match ($usage) {
        'card' => [
            reis_media_variant_path($full, '_card'),
            $full,
        ],
        default => [
            reis_media_variant_path($full, '_hero'),
            $full,
            reis_media_variant_path($full, '_card'),
        ],
    };

    $src = $full;
    foreach ($candidates as $candidate) {
        if ($docRoot !== '' && is_file($docRoot . '/' . $candidate)) {
            $src = $candidate;
            break;
        }
    }

    $srcsetParts = [];
    foreach (array_unique([$full, reis_media_variant_path($full, '_hero'), reis_media_variant_path($full, '_card')]) as $candidate) {
        if ($docRoot === '' || !is_file($docRoot . '/' . $candidate)) {
            continue;
        }
        $w = reis_media_image_width($docRoot . '/' . $candidate);
        if ($w > 0) {
            $srcsetParts[$w] = reis_media_public_url($candidate) . ' ' . $w . 'w';
        }
    }
    ksort($srcsetParts);

    return [
        'src' => reis_media_public_url($src),
        'srcset' => implode(', ', $srcsetParts),
        'mime' => reis_media_mime($src),
    ];
}

function reis_media_mime(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return match ($ext) {
        'webp' => 'image/webp',
        'png' => 'image/png',
        'gif' => 'image/gif',
        default => 'image/jpeg',
    };
}

function reis_media_image_width(string $absolutePath): int
{
    if (!is_file($absolutePath)) {
        return 0;
    }
    $info = @getimagesize($absolutePath);
    if (!is_array($info)) {
        return 0;
    }

    return (int) ($info[0] ?? 0);
}

/** Genereer _hero (1600px) en _card (800px) na upload. */
function reis_media_generate_variants(string $absoluteSource): void
{
    if (!is_file($absoluteSource) || !function_exists('imagecreatefromjpeg')) {
        return;
    }

    $info = @getimagesize($absoluteSource);
    if (!is_array($info)) {
        return;
    }

    [$width, $height, $type] = $info;
    $width = (int) $width;
    $height = (int) $height;
    if ($width <= 0 || $height <= 0) {
        return;
    }

    $src = match ($type) {
        IMAGETYPE_PNG => @imagecreatefrompng($absoluteSource),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absoluteSource) : false,
        default => @imagecreatefromjpeg($absoluteSource),
    };
    if (!$src) {
        return;
    }

    $dir = dirname($absoluteSource);
    $base = pathinfo($absoluteSource, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($absoluteSource, PATHINFO_EXTENSION));

    foreach ([['_hero', 1600], ['_card', 800]] as [$suffix, $maxW]) {
        if ($width <= $maxW) {
            @copy($absoluteSource, $dir . '/' . $base . $suffix . '.' . $ext);
            continue;
        }
        $newW = $maxW;
        $newH = (int) max(1, round($height * ($newW / $width)));
        $dst = imagecreatetruecolor($newW, $newH);
        if (!$dst) {
            continue;
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        $target = $dir . '/' . $base . $suffix . '.' . $ext;
        match ($ext) {
            'png' => imagepng($dst, $target, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $target, 82) : false,
            default => imagejpeg($dst, $target, 86),
        };
        imagedestroy($dst);
    }

    imagedestroy($src);
}
