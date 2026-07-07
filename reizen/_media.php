<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/beheer/includes/reis_media.php';

function busreis_foto_media(string $fotoPad, string $usage = 'hero'): array
{
    return reis_media_resolve($fotoPad, $usage);
}

function busreis_foto_picture(string $fotoPad, string $alt, string $class, string $usage = 'hero', string $loading = 'eager'): string
{
    if (trim($fotoPad) === '') {
        return '';
    }

    $media = reis_media_resolve($fotoPad, $usage);
    $src = htmlspecialchars($media['src'], ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    $sizes = $usage === 'card'
        ? '(max-width: 700px) 100vw, 400px'
        : '(max-width: 900px) 100vw, 1200px';
    $srcset = trim($media['srcset']);
    $srcsetAttr = $srcset !== '' ? ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '"' : '';
    $sizesAttr = $srcset !== '' ? ' sizes="' . $sizes . '"' : '';
    $extra = $loading === 'lazy' ? ' loading="lazy" decoding="async"' : ' fetchpriority="high" decoding="async"';

    return '<img class="' . $class . '" src="' . $src . '"' . $srcsetAttr . $sizesAttr
        . ' alt="' . $alt . '"' . $extra . '>';
}
