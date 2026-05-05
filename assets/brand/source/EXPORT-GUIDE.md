# BusAI Export Guide

Praktische exportinstellingen voor Figma en Adobe Illustrator, gericht op:

- consistente brand-assets
- scherpe rendering
- kleine bestandsgrootte voor web

---

## 1) Variants die je altijd exporteert

### Logo varianten

- `busai-logo-horizontal` (hoofdlogo)
- `busai-logo-icon` (icon-only)
- `busai-logo-dark` (donkere versie op lichte achtergrond)
- `busai-logo-white` (witte versie op donkere achtergrond)

### Aanbevolen bestandsset per variant

- `SVG` (master voor web/UI)
- `PNG` (`2048`, `1024`, `512`, `256`, `128`, `64`, `32`)

### Favicon/PWA

- `favicon-16.png`
- `favicon-32.png`
- `apple-touch-icon.png` (`180x180`)
- `android-chrome-192.png`
- `android-chrome-512.png`
- `favicon.ico` (bevat minimaal 16/32/48)

---

## 2) Figma exportinstellingen

## Voorbereiding in Figma

- Zet elke variant in een eigen frame met duidelijke naam.
- Gebruik alleen vectorvormen en tekst naar outlines als je 100% vaste vorm wilt.
- Verwijder ongebruikte lagen/effecten.
- Houd achtergrond transparant, behalve expliciete light/dark previews.

## SVG export (Figma)

- Selecteer frame/logo.
- `Export -> SVG`
- Instellingen:
  - `Outline text`: **Aan** (aanrader voor merkconsistentie)
  - `Include id attribute`: **Uit**
  - `Simplify stroke`: **Aan** (tenzij het vormverschil geeft)
  - `Use absolute bounds`: **Uit**
  - `SVG size`: `1x`

Bestandsnaamvoorbeeld:

- `busai-logo-horizontal.svg`

## PNG export (Figma)

- Formaat per variant exporteren:
  - `1x`, `2x`, `4x` of direct custom pixel sizes
- Gebruik transparante achtergrond.
- Export exact op doelafmeting voor favicon/pwa.

Aanbevolen set:

- `2048`, `1024`, `512`, `256`, `128`, `64`, `32`

---

## 3) Adobe Illustrator exportinstellingen

## Voorbereiding in Illustrator

- Werk op artboards per variant.
- `Type -> Create Outlines` voor definitieve logo-export.
- `Object -> Path -> Outline Stroke` alleen als nodig.
- Verwijder hidden/unused layers.

## SVG export (Illustrator)

`File -> Export -> Export As -> SVG`

Aanbevolen instellingen:

- `SVG Profile`: `SVG 1.1`
- `Styling`: `Presentation Attributes`
- `Font`: `Convert to Outlines`
- `Images`: `Embed`
- `Object IDs`: `Layer Names` of `Minimal`
- `Decimal`: `2` of `3`
- `Minify`: **Aan**
- `Responsive`: **Aan**
- `Preserve Illustrator Editing Capabilities`: **Uit**

## PNG export (Illustrator)

`File -> Export -> Export for Screens`

- Scale:
  - `1x`, `2x`, `4x` of custom px
- Anti-aliasing:
  - `Art Optimized`
- Background:
  - `Transparent`

---

## 4) Favicon generatie

Start met `busai-logo-icon`:

- Maak een vereenvoudigde versie als detail dichtloopt op klein formaat.
- Exporteer:
  - `16x16`
  - `32x32`
  - `48x48`
- Bouw daarna `favicon.ico` uit die PNG's (via tooling of converter).

Tip:

- Controleer favicon op browser tabs met zowel lichte als donkere OS-themes.

---

## 5) Web-optimalisatie (cruciaal)

## SVG optimalisatie

Na export SVG altijd optimaliseren:

- verwijder metadata
- verwijder comments
- verkort paden waar mogelijk
- strip onnodige ids/classes

Aanbevolen tool:

- [SVGO](https://github.com/svg/svgo)

Voorbeeldcommando:

```bash
npx svgo assets/brand/svg/busai-logo-horizontal.svg -o assets/brand/svg/busai-logo-horizontal.min.svg
```

Aanbevolen aanpak:

- Bewaar beide:
  - `*.svg` (bron/export)
  - `*.min.svg` (webproductie)

## PNG optimalisatie

Gebruik lossless compressie:

- `oxipng` of `pngquant` (voor web, met kwaliteitscheck)

Voorbeeld:

```bash
oxipng -o 4 -s assets/brand/png/busai-logo-horizontal-512.png
```

## Browser-performance tips

- Gebruik SVG voor logo in header/footer waar mogelijk.
- Voor hero-logo:
  - lazy-load als het niet above-the-fold is
  - anders preloaden.
- Stel correcte `width`/`height` attributen in om layout shift te voorkomen.

---

## 6) QA checklist vóór livegang

- Is logo scherp op 1x en 2x displays?
- Is contrast voldoende op witte en donkere achtergronden?
- Werkt icon-only op `32x32` zonder dichtlopen?
- Zijn SVG's geoptimaliseerd (`*.min.svg`)?
- Zijn bestandsnamen consistent en lowercase?

---

## 7) Bestandsnaamconventie

Gebruik altijd:

- lowercase
- koppeltekens
- duidelijke variant + grootte

Voorbeelden:

- `busai-logo-horizontal.svg`
- `busai-logo-horizontal.min.svg`
- `busai-logo-horizontal-512.png`
- `busai-logo-icon-32.png`
- `favicon-32.png`
