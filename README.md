# Match Day Generator

PHP + Bootstrap 5 aplikácia na generovanie zápasových grafík pre futbalové kluby (dáta zo Sportnet / Futbalnet SFZ).

Tri typy obrázkov: **Match day** (pozvánka), **Výsledok** a **Strelci**.

## Funkcie
- Vyhľadanie klubu a zápasov sezóny, filter nadchádzajúce / odohraté
- Generovanie obrázka priamo v prehliadači (HTML canvas) – stiahnutie PNG, kopírovanie, zdieľanie
- **Drag & drop editor šablón** – pozície prvkov, písmo, farby, obrys, tieň, vlastné texty a podklady
- Nastavenie zoznamu strelcov (osadenie, rozostupy, šírka stĺpca, automatické zmenšenie)
- Vlastné pozadie a rozmer obrázka, 26 Google Fonts + nahranie vlastného písma
- Všetky nastavenia sa ukladajú **v prehliadači** (localStorage + IndexedDB), záloha cez export/import JSON
- Ukladanie vygenerovaných obrázkov na server + galéria (obrázky z daného prehliadača): náhľady, filter, hľadanie, zoradenie, prezeranie na celú obrazovku s listovaním, stiahnutie, zdieľanie
- Správca (heslo v `config.local.php`): galéria všetkých prehliadačov, mazanie obrázkov, log generovania
- Log generovania **bez osobných údajov** – žiadne IP adresy, User-Agent ani údaje o zariadení; iba čo a kedy sa vygenerovalo, rodina prehliadača/systému a anonymné ID
- PWA – inštalácia do zariadenia, offline stránka, svetlý / tmavý režim

## Inštalácia
1. Nahrajte priečinok na server (PHP 8.1+, rozšírenia `gd`, `curl`, `mbstring`; Apache s `mod_rewrite`).
2. Ak aplikácia nebeží v `/match-day`, upravte `BASE_PATH` v `config.php`.
3. Skopírujte `config.local.example.php` ako **`config.local.php`** a vyplňte heslo správcu (`GALLERY_ADMIN_KEY`).
   Tento súbor sa do gitu nenahráva.
4. Priečinky `storage/generated` a `storage/logs` musia byť zapisovateľné pre PHP (práva 775).
5. PWA (inštalácia aplikácie) funguje iba cez **https**.

## Štruktúra
| Cesta | Popis |
|---|---|
| `index.php`, `config.php` | vstupný bod a konfigurácia (routy, cesty, API) |
| `pages/` | stránky (`index`, `season-detail`, `generator`, `settings`, `gallery` …) |
| `pages/api/` | JSON / obrázkové API: logá, góly, uloženie obrázka, galéria |
| `pages/pwa/` | manifest a service worker |
| `assets/js/renderer.js` | kreslenie obrázka (spoločné pre editor aj generátor) |
| `assets/js/editor.js` | drag & drop editor šablón |
| `assets/js/storage.js` | ukladanie nastavení v prehliadači |
| `storage/` | uložené obrázky a logy (mimo gitu) |

## Stránky
| URL | Popis |
|---|---|
| `/` | výber sezóny a klubu |
| `/match-day-season-detail` | zoznam zápasov |
| `/generator?type=matchday\|result\|goals&…` | náhľad a stiahnutie obrázka |
| `/nastavenia` | editor šablón |
| `/galeria` | uložené obrázky |
| `/spravca` | prihlásenie a prehľad správcu |
| `/galeria?vsetky=1` | galéria všetkých prehliadačov (iba správca) |
| `/log` | log generovania (iba správca) |
