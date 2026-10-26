# Match Day Generator

PHP + Bootstrap 5 aplikácia na generovanie zápasových grafík pre futbalové kluby (dáta zo Sportnet / Futbalnet SFZ).

Štrnásť typov obrázkov:
- k zápasu: **Match day**, **Zostava**, **Výsledok**, **Strelci**, **Súhrn zápasu**, **Vzájomné zápasy**, **Forma**
- k družstvu: **Súpiska**, **Tabuľka**, **Kolo** (program / výsledky), **Štatistiky**
- k hráčovi: **Profil hráča**, **Hráč zápasu**, **Jubileum** (s fotkou hráča uloženou v prehliadači)

## Funkcie
- **Detail zápasu**: priebeh, štatistika vzájomných zápasov, forma, delegované osoby, štadión, zostavy, H2H a tabuľka – pri každej časti tlačidlo na obrázok; kluby, družstvá, hráči a zápasy sú navzájom prelinkované
- **Globálne hľadanie** v menu (kluby a hráči zo Sportnetu, prezerané družstvá a hráči, stránky; skratka `/` alebo Ctrl+K)
- Stránka klubu: záložky družstiev, výber sezóny, najbližší a posledný zápas navrchu, filter nadchádzajúce / odohraté
- Zostava na zápas (základ, náhradníci, tréner) zo zápisu o stretnutí a súpiska družstva za sezónu zo zápisov odohratých zápasov; hráčov možno ručne upraviť (uloží sa v prehliadači)
- Stránky: **zápis o stretnutí** (góly, karty, striedania, polčas, rozhodcovia, diváci, zostavy), **prehľad družstva** (štatistiky, hráči s minútami, oficiálna tabuľka, kolá celej súťaže) a **profil hráča** (štatistiky a zápasy sezóny s minútami, kariéra)
- Generovanie obrázka priamo v prehliadači (HTML canvas) – stiahnutie PNG, kopírovanie, zdieľanie
- **Drag & drop editor šablón** – pozície prvkov, písmo, farby, obrys, tieň, vlastné texty a podklady
- Nastavenie zoznamu strelcov (osadenie, rozostupy, šírka stĺpca, automatické zmenšenie)
- Vlastné pozadie a rozmer obrázka, 26 Google Fonts + nahranie vlastného písma
- Všetky nastavenia sa ukladajú **v prehliadači** (localStorage + IndexedDB), záloha cez export/import JSON
- Ukladanie vygenerovaných obrázkov na server + galéria (obrázky z daného prehliadača): náhľady, filter, hľadanie, zoradenie, prezeranie na celú obrazovku s listovaním, stiahnutie, zdieľanie
- Správca (predvolené heslo `asd123`, zmena v `config.local.php`): galéria všetkých prehliadačov, mazanie obrázkov, log generovania
- Log generovania **bez osobných údajov** – žiadne IP adresy, User-Agent ani údaje o zariadení; iba čo a kedy sa vygenerovalo, rodina prehliadača/systému a anonymné ID
- Podrobný návod priamo v aplikácii (`/navod`, odkaz v menu) so snímkami obrazovky
- PWA – inštalácia do zariadenia, offline stránka, svetlý / tmavý režim

## Inštalácia
1. Nahrajte priečinok na server (PHP 8.1+, rozšírenia `gd`, `curl`, `mbstring`; Apache s `mod_rewrite`).
2. Ak aplikácia nebeží v `/match-day`, upravte `BASE_PATH` v `config.php`.
3. Skopírujte `config.local.example.php` ako **`config.local.php`** a zmeňte predvolené heslo správcu `asd123` (`GALLERY_ADMIN_KEY`).
   Tento súbor sa do gitu nenahráva.
4. Priečinky `storage/generated` a `storage/logs` musia byť zapisovateľné pre PHP (práva 775).
5. PWA (inštalácia aplikácie) funguje iba cez **https**.

## Dáta zo Sportnetu
Používa [Sportnet Súťaže API v2](https://sutaze.api.sportnet.online/api/v2/docs/) (adresy v `config.php`):

| Konštanta | Endpoint | Na čo |
|---|---|---|
| `API_CLUB_SEARCH` | `api.sportnet.online/v1/ppo/futbalsfz.sk/related-ppos` | hľadanie klubov |
| `API_CLUB_TEAMS` | `/public/{appSpace}/teams` | družstvá klubu (aj `issf_club_…`) |
| `API_MATCHES` | `/futbalnet/matches?teamId=` | zápasy družstva |
| `API_MATCH` | `/matches/{id}` | zápis o stretnutí (zostavy, góly, karty, striedania) |
| `API_PART_TABLE` | `/public/competitions/{id}/parts/{partId}/results` | oficiálna tabuľka |
| `API_COMP_PLAYERS` | `/public/competitions/{id}/players?teamId=&partId=` | minúty, štarty, nástupy hráčov |
| `API_PLAYER_STATS` | `/player/{sportnetId}/statistics` | kariéra hráča |
| `API_H2H` | `/futbalnet/h2h-matches?teamAppSpaces=A&teamAppSpaces=B` | vzájomné zápasy klubov |
| `API_PLAYER_MATCHES` | `/player-matches/ppo/{klub}/player/{id}` | posledné zápasy hráča |
| `API_USER`, `API_USER_CLUBS`, `API_PLAYER_SEARCH` | `api.sportnet.online/v1/…/users` | hráč (fotka, rok narodenia, kluby) a hľadanie hráčov |

## Štruktúra
| Cesta | Popis |
|---|---|
| `index.php`, `config.php` | vstupný bod a konfigurácia (routy, cesty, API) |
| `pages/` | stránky (`index`, `season-detail`, `generator`, `settings`, `gallery` …) |
| `pages/api/` | JSON / obrázkové API: logá, zápis o stretnutí (góly, zostavy), súpiska, uloženie obrázka, galéria |
| `pages/pwa/` | manifest a service worker |
| `assets/js/renderer.js` | kreslenie obrázka (spoločné pre editor aj generátor) |
| `assets/js/editor.js` | drag & drop editor šablón |
| `assets/js/storage.js` | ukladanie nastavení v prehliadači |
| `includes/sportnet.php` | spracovanie dát Sportnetu: zápis, tabuľka, kolá, štatistiky, hráči |
| `storage/` | uložené obrázky a logy (mimo gitu) |

## Stránky
| URL | Popis |
|---|---|
| `/` | výber sezóny a klubu |
| `/match-day-season-detail` | zoznam zápasov |
| `/generator?type=matchday\|result\|goals&…` | náhľad a stiahnutie obrázka |
| `/nastavenia` | editor šablón |
| `/galeria` | uložené obrázky |
| `/navod` | podrobný návod so snímkami obrazovky |
| `/zapas?matchID=…` | celý zápis o stretnutí |
| `/druzstvo?team=…` | prehľad družstva (štatistiky, hráči, tabuľka, kolá) |
| `/hrac?team=…&p=…` | profil hráča |
| `/spravca` | prihlásenie a prehľad správcu |
| `/galeria?vsetky=1` | galéria všetkých prehliadačov (iba správca) |
| `/log` | log generovania (iba správca) |
