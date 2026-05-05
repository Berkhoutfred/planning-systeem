<?php
declare(strict_types=1);

/**
 * Tekst voor klant-PDF (offerte/bevestiging): geen interne wizard-dump tonen.
 * Oude records beginnen met "[Offerte-aanvraag webformulier]" — die blijven leeg op de PDF.
 */
function pdf_filter_instructie_voor_klant(?string $instructieKantoor): string
{
    if ($instructieKantoor === null) {
        return '';
    }
    $t = trim($instructieKantoor);
    if ($t === '') {
        return '';
    }
    if (str_starts_with($t, '[Offerte-aanvraag webformulier]')) {
        return '';
    }

    return $t;
}
