# Match Day Generator

PHP + Bootstrap 5 aplikácia na generovanie zápasových grafík pre futbalové kluby (dáta zo Sportnet / Futbalnet SFZ).

Devätnásť typov obrázkov, každý ako štvorcový príspevok aj **story 9:16**, v kvalite **HD 2×** (2000 × 2000, story 2160 × 3840; pozadia šablón v 2000 px) alebo štandard (1000 × 1000); plagát A4 (1240 × 1754, PDF na tlač 300 DPI):
- ku klubu: **Víkend klubu** (program / výsledky všetkých družstiev v týždni)
- k zápasu: **Match day**, **Zostava**, **Výsledok**, **Strelci**, **Súhrn zápasu**, **Vzájomné zápasy**, **Forma**, **Plagát A4** (pozvánka na tlač s QR kódom)
- k družstvu: **Súpiska**, **Tabuľka**, **Kolo** (program / výsledky), **Štatistiky**, **Hráč / strelec mesiaca**, **Koniec sezóny** (miesto, body, skóre, najlepší strelec, najviac minút)
- k hráčovi: **Profil hráča**, **Hráč zápasu**, **Jubileum**, **Narodeniny** (s fotkou hráča zo Sportnetu alebo uloženou v prehliadači)

## Funkcie
- **Detail zápasu**: priebeh, štatistika vzájomných zápasov, forma, delegované osoby, štadión, zostavy, H2H a tabuľka – pri každej časti tlačidlo na obrázok; kluby, družstvá, hráči a zápasy sú navzájom prelinkované
- **Globálne hľadanie** v menu (kluby a hráči zo Sportnetu, prezerané družstvá a hráči, stránky; skratka `/` alebo Ctrl+K)
- Stránka klubu: záložky družstiev, výber sezóny, najbližší a posledný zápas navrchu, filter nadchádzajúce / odohraté
- Zostava na zápas (základ, náhradníci, tréner) zo zápisu o stretnutí a súpiska družstva za sezónu zo zápisov odohratých zápasov; hráčov možno ručne upraviť (uloží sa v prehliadači)
- Stránky: **zápis o stretnutí** (góly, karty, striedania, polčas, rozhodcovia, diváci, zostavy), **prehľad družstva** (štatistiky, hráči s minútami, oficiálna tabuľka, kolá celej súťaže) a **profil hráča** (štatistiky a zápasy sezóny s minútami, kariéra)
- Generovanie obrázka priamo v prehliadači (HTML canvas) – stiahnutie PNG, kopírovanie, zdieľanie, **všetky typy naraz v ZIP**
- **Profil hráča**: prepínač sezóny a družstva (zo zápasov hráča), filter zápasov (doma/vonku, V-R-P, základ/striedal, jeseň/jar, s gólom, s kartou) s prepočítaným súčtom, komentáre zo zápisu (dôvody kariet)
- **Priebeh zápasu** ako časová os (domáci vľavo, hostia vpravo, polčasy) s komentármi zo zápisu – dôvod karty, druh gólu, striedajúci hráč
- **Plagát A4** k zápasu s **QR kódom** na detail zápasu – stiahnutie ako PDF na tlač (vlastný generátor QR `assets/js/qr.js` a PDF `MD.pdf` v `renderer.js`, bez knižníc); prvok *QR kód* je dostupný v editore šablón
- **Plávajúce tlačidlo Vytvoriť obrázok** na stránkach klubu, družstva, zápasu a hráča: panel so všetkými obrázkami pre danú stránku a **odporúčaniami podľa situácie** (Match day pred zápasom, výsledok po zápase, kolo a tabuľka po kole, hráč mesiaca na začiatku mesiaca, víkend klubu …); logika v `includes/create.php`, panel `assets/js/create.js`
- Každý PNG obsahuje metadáta (`Software: Match Day Generator`, názov, popis, zdroj a čas vytvorenia) – v stiahnutí, ZIP, zdieľaní aj na serveri
- **Text príspevku** na Facebook / Instagram (skóre, strelci, súťaž, hashtagy) s tlačidlom Kopírovať
- **Značka klubu** (`/znacka`): logo klubu a pás sponzorov sa vložia do každého obrázka; **klubové farby** prefarbia predvolené pozadia aj prvky šablón (predvoľby alebo farby zistené z loga)
- **Môj klub** – najbližšie zápasy a posledné výsledky obľúbeného klubu priamo na úvodnej stránke s tlačidlami na obrázky
- **Verejná stránka obrázka** (`/zdielaj?f=…`) s náhľadom pre sociálne siete (Open Graph) – odkaz z generátora aj galérie
- **Drag & drop editor šablón** – pozície prvkov, písmo, farby, obrys, tieň, vlastné texty a podklady
- Nastavenie zoznamu strelcov (osadenie, rozostupy, šírka stĺpca, automatické zmenšenie)
- Jednotné písmo **Barlow Condensed** (OFL, v `assets/fonts`) pre obrázky, text stránky v lokálnom písme Inter (OFL); ďalších 25 Google Fonts sa načíta iba ak ich šablóna použije + nahranie vlastného písma
- Moderné predvolené pozadia (Match day, Výsledok, Strelci a panel pre ostatné obrázky)
- Všetky nastavenia sa ukladajú **v prehliadači** (localStorage + IndexedDB), záloha cez export/import JSON
- Ukladanie vygenerovaných obrázkov na server + galéria (obrázky z daného prehliadača): náhľady, filter, hľadanie, zoradenie, prezeranie na celú obrazovku s listovaním, stiahnutie, zdieľanie
- Správca (predvolené heslo `asd123`, zmena v `config.local.php`): galéria všetkých prehliadačov, mazanie obrázkov, log generovania, **kontrola Sportnetu** (`/kontrola` – overenie všetkých zdrojov dát a vymazanie cache)
- Log generovania **bez osobných údajov** – žiadne IP adresy, User-Agent ani údaje o zariadení; iba čo a kedy sa vygenerovalo, rodina prehliadača/systému a anonymné ID
- Podrobný návod priamo v aplikácii (`/navod`, odkaz v menu) so snímkami obrazovky
- PWA – inštalácia do zariadenia, offline stránka, svetlý / tmavý režim

## Inštalácia
1. Nahrajte priečinok na server (PHP 8.1+, rozšírenia `gd`, `curl`, `mbstring`; Apache s `mod_rewrite`).
2. Ak aplikácia nebeží v `/match-day`, upravte `BASE_PATH` v `config.php`.
3. Skopírujte `config.local.example.php` ako **`config.local.php`** a zmeňte predvolené heslo správcu `asd123` (`GALLERY_ADMIN_KEY`).
   Tento súbor sa do gitu nenahráva.
4. Priečinok `storage` (`generated`, `logs`, `cache`, `private`) musí byť zapisovateľný pre PHP (práva 775). `cache` a `private` sú z webu nedostupné.
5. PWA (inštalácia aplikácie) funguje iba cez **https**.
6. **Nočná úloha** (odporúčané) – obnoví cache Sportnetu pre používané družstvá, zmaže staré obrázky a skontroluje Sportnet (pri chybe pošle e-mail na `ADMIN_EMAIL`):
   ```
   10 4 * * *  php /cesta/k/match-day/cli/cron.php > /dev/null 2>&1
   ```
   Bez prístupu ku cronu: nastavte `CRON_KEY` v `config.local.php` a vo webcron službe hostingu volajte `https://vas-web/match-day/cron?key=…`.
7. **E-maily cez SMTP** – do `config.local.php` doplňte `ADMIN_EMAIL`, `SMTP_HOST`, `SMTP_PORT` (465 = SSL, 587 = STARTTLS), `SMTP_USER`, `SMTP_PASS` (a `MAIL_FROM`). Nastavenie overíte v časti Správca → **Poslať skúšobný e-mail**. Bez `SMTP_HOST` sa použije funkcia `mail()` servera.

## Nastavenia v aplikácii
Po prihlásení správcu: **Správca → Nastavenia** (`/nastavenia-spravcu`) – názov aplikácie, časové pásmo, vývojový režim, log, e-mail a SMTP (so skúšobným e-mailom), úložisko a limity, povolené domény lôg, overovanie HTTPS, nočná úloha (kľúč webcronu, klub na kontrolu). Ukladá sa do `storage/private/settings.json` (z webu nedostupné). Hodnota zadaná v `config.local.php` má prednosť a v aplikácii je uzamknutá – ak ju chcete meniť v aplikácii, riadok z `config.local.php` odstráňte. Heslo správcu sa mení v prehľade správcu.

## Bezpečnosť a rýchlosť
- Heslo správcu sa ukladá iba ako hash (`storage/private/admin.json`), zmena hesla priamo v aplikácii; prihlasovanie má limit pokusov aj podľa IP.
- Spojenia so Sportnetom overujú HTTPS certifikát (`SSL_VERIFY`; záložný balík CA `includes/cacert.pem`, vlastný cez `SSL_CAINFO`).
- Proxy na logá (`api-logo`) sťahuje iba z povolených domén (`IMAGE_HOSTS` + domény z odpovedí Sportnetu), iba z verejných IP, každé presmerovanie sa overuje a spojenie ide na overenú IP.
- Ukladanie obrázkov má limit za hodinu (`SAVE_LIMIT_IP`, `SAVE_LIMIT_CLIENT`), celkovú kvótu (`STORAGE_MAX_MB`) a automatické mazanie starých (`STORAGE_KEEP_DAYS`).
- Bezpečnostné hlavičky: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (na https).
- IP adresy sa nikam neukladajú – limity používajú iba HMAC hash s tajným kľúčom servera.
- Cache Sportnetu: po vypršaní sa hneď zobrazí posledná verzia a nová sa stiahne na pozadí (po odoslaní stránky); pri výpadku Sportnetu sa zobrazí upozornenie.
- Logá v zoznamoch sú zmenšené WebP náhľady (`api-logo?s=64`), pozadia šablón WebP (~40 kB), písma a ikony lokálne (žiadne Google Fonts / CDN), kompresia a dlhá cache v `.htaccess`.

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
| `assets/js/storage.js` | ukladanie nastavení v prehliadači (šablóny, pozadia, fotky, značka klubu) |
| `assets/js/brand.js` | stránka Značka klubu |
| `includes/create.php`, `assets/js/create.js` | plávajúce tlačidlo Vytvoriť obrázok a odporúčania |
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
| `/hrac?team=…&p=…` alebo `/hrac?id=…` | profil hráča |
| `/znacka` | logo klubu, sponzori a klubové farby |
| `/zdielaj?f=…` | verejná stránka uloženého obrázka (náhľad pre sociálne siete) |
| `/spravca` | prihlásenie a prehľad správcu |
| `/galeria?vsetky=1` | galéria všetkých prehliadačov (iba správca) |
| `/log` | log generovania (iba správca) |
| `/kontrola` | kontrola zdrojov dát Sportnetu (iba správca) |
| `/cron?key=…` | nočná úloha cez webcron (iba s `CRON_KEY`) |
