# Geheugen: verzamelofferte, kopie-offerte, losse pakketdagen

Intern naslagwerk voor opschoning of vervolg. Laatste relevante traject: meerdere richtingen rond “meerdere offertes / ritten in één offerte”.

---

## Twee verschillende concepten (niet door elkaar halen)

| Concept | Betekenis | Status traject |
|--------|-----------|----------------|
| **Losse pakketdagen** | Meerdere **heen-/travel-dagen** binnen **één** `calculaties`-rij (`route_v2_json`, vlag `flags.losse_rijdagen_pakket`, UI “Meerdere losse rijdagen”). | **Standaard UIT** voor alle tenants. Alleen aan via `.env` `CALCULATIE_LOSSE_PAKKET_DAGEN_ENABLED` (truthy). Bij uit: strip in `calculatie_route_v2_normalize_payload()` + migratie bij openen bewerken. |
| **Verzamelofferte** | Meerdere **aparte** calculaties selecteren → **één PDF** met voorblad + per offerte dezelfde body als enkel-offerte. | **Actief** na migratie SQL. Eigen tabellen, geen merge van calculaties. |

“Wat niet gelukt is” in de zin van *niet de gekozen standaard voor iedereen*: **losse pakketdagen staan niet aan als productfeature**; de code blijft wel in de repo achter de vlag, voor eventueel herstel of latere verwijdering.

---

## Bestanden en onderdelen (naslag)

### Feature-flag & route opschoning

- `beheer/calculatie/includes/calculatie_feature_flags.php` — `calculatie_feature_losse_pakket_dagen_enabled()`
- `beheer/calculatie/includes/route_v2.php` — o.a. `calculatie_route_v2_remove_losse_pakket_package_data()`, aanroep onderaan `calculatie_route_v2_normalize_payload()` als feature uit
- `beheer/calculatie/calculaties_bewerken.php` — bij laden: decode + eventueel `UPDATE route_v2_json` als gestript anders is dan ruwe JSON
- `beheer/calculatie/maken.php` — wrap + `window.CALC_LOSSE_PAKKET_DAGEN_ENABLED`
- `beheer/calculatie/js/meerdere_losse_rijdagen.js` — respecteert `CALC_LOSSE_PAKKET_DAGEN_ENABLED`

### Verzamelofferte (bundel-PDF)

- Migratie: `migrations/20260513_offerte_verzamelingen.sql` — tabellen `offerte_verzamelingen`, `offerte_verzameling_items`
- `beheer/calculatie/verzamelofferte.php` — selectie + POST bundle
- `beheer/calculatie/verzamelofferte_pdf.php` — PDF-output
- `beheer/calculaties.php` — link “Verzamelofferte” + kopie-icoon per calculatie

### Offerte kopiëren (nieuwe datum)

- `beheer/calculatie/calculatie_kopie.php` — formulier + CSRF
- `beheer/calculatie/includes/calculatie_dupliceren.php` — dupliceren rij + regels, token, datums, `route_v2` verschuiven

### PDF gedeeld met enkel-offerte

- `beheer/calculatie/includes/offerte_pdf_layout.php` — helpers + `OffertePDF` + `offerte_pdf_render_offer_body()`
- `beheer/calculatie/pdf_offerte.php` — dunne wrapper rond layout + presentatie

### Overig

- `beheer/calculatie/includes/ui_build.php` — versienr + releasedatum (`date` YYYY-MM-DD) voor cache-busting UI

---

## Omgeving (.env), geen SQL voor de vlag

- `CALCULATIE_LOSSE_PAKKET_DAGEN_ENABLED` — optioneel; weg of `0` = uit voor iedereen.

---

## SQL dat wél nodig was (alleen verzamelofferte)

Zie `migrations/20260513_offerte_verzamelingen.sql`. Zonder deze tabellen werkt verzamelofferte niet (pagina toont melding).

---

## Later alles opruimen: checklist

Gebruik alleen als je bewust **losse pakketdagen** en/of **verzamelofferte** uit de codebase wilt halen.

### A. Alleen “losse pakketdagen” verwijderen

1. Verwijder of leeg UI/JS: `meerdere_losse_rijdagen.js`, verwijzingen in `maken.php` / `calculaties_bewerken.php`.
2. Verwijder `calculatie_route_v2_remove_losse_pakket_package_data` + tak in `calculatie_route_v2_normalize_payload`; verwijder `require` feature_flags in `route_v2.php` als niets anders het gebruikt.
3. Verwijder `calculatie_feature_flags.php` en migratie-/load-logica in `calculaties_bewerken.php` die op die flag leunt.
4. **Data**: bestaande `route_v2_json` met extra travel-dagen blijft in DB tot opgeslagen/herberekend; optioneel bulk-UPDATE/script apart ontwerpen (niet in dit document uitgewerkt).

### B. Alleen verzamelofferte verwijderen

1. UI: links in `calculaties.php`; verwijder `verzamelofferte.php`, `verzamelofferte_pdf.php`.
2. SQL: `DROP TABLE IF EXISTS offerte_verzameling_items; DROP TABLE IF EXISTS offerte_verzamelingen;` (volgorde: eerst items, dan bundels).
3. Migratiebestand kan blijven voor geschiedenis of verwijderd worden — consistent met git-beleid.

### C. Kopie-offerte behouden of niet

- Zelfstandig nuttig; alleen verwijderen als je de hele kopie-flow niet wilt: `calculatie_kopie.php`, `calculatie_dupliceren.php`, link op `calculaties.php`.

### D. PDF-layout split terugdraaien

- Samenvoegen van `offerte_pdf_layout.php` terug in `pdf_offerte.php` als je één bestand weer wilt (grote diff).

---

## Bekende beperkingen / randjes

- **Kopie**: `tussendagen_meta` e.d. wordt mee-gekopieerd; diep datummatchen op alle JSON-kolommen is bewust beperkt gehouden.
- **Verzamel-PDF**: tenant komt uit sessie/db-context zoals andere beheer-PDF’s; geen publiek token op deze bundle-PDF in eerste versie.
- **Losse pakketdagen uit**: client-side payload kan kort valse `days` hebben tot server normaliseert; bron van waarheid blijft server-normalize bij save.

---

*Aangemaakt op verzoek als intern geheugen voor latere opschoning of uitbreiding.*
