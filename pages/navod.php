<?php
	/**
	 * Podrobný návod na používanie aplikácie (so snímkami obrazovky z assets/img/navod/).
	 */

	/** Obrázok v návode – klikom sa zväčší */
	function guide_shot($file, $alt, $caption = '', $class = '') {
		$path = ASSETS_DIR . 'img/navod/' . $file;
		[$w, $h] = is_file($path) ? (@getimagesize($path) ?: [1600, 1000]) : [1600, 1000];
		$src = asset('img/navod/' . $file);
		$out  = '<figure class="guide-shot ' . $class . '">';
		$out .= '<button type="button" class="guide-shot-btn" data-zoom="' . plain($src) . '" data-caption="' . plain(strip_tags($caption ?: $alt)) . '" aria-label="Zväčšiť obrázok: ' . plain($alt) . '">';
		$out .= '<img src="' . plain($src) . '" alt="' . plain($alt) . '" width="' . (int)$w . '" height="' . (int)$h . '" loading="lazy" decoding="async">';
		$out .= '<span class="guide-zoom"><i class="bi bi-zoom-in"></i></span></button>';
		if ($caption) $out .= '<figcaption>' . $caption . '</figcaption>';
		return $out . '</figure>';
	}

	$toc = [
		'uvod'       => ['bi-info-circle', 'Čo aplikácia robí'],
		'rychly'     => ['bi-lightning-charge', 'Rýchly štart'],
		'vyhladanie' => ['bi-search', '1. Vyhľadanie klubu'],
		'zapasy'     => ['bi-list-ul', '2. Zoznam zápasov'],
		'generator'  => ['bi-image', '3. Generovanie obrázka'],
		'strelci'    => ['bi-list-ol', 'Strelci gólov'],
		'zostava'    => ['bi-person-lines-fill', 'Zostava na zápas'],
		'supiska'    => ['bi-people', 'Súpiska družstva'],
		'hraci'      => ['bi-pencil-square', 'Úprava hráčov'],
		'zapis'      => ['bi-file-text', 'Detail zápasu'],
		'druzstvo'   => ['bi-bar-chart-line', 'Prehľad družstva'],
		'obr-druzstvo' => ['bi-table', 'Tabuľka, kolo, štatistiky'],
		'vikend'       => ['bi-calendar2-week', 'Víkend klubu'],
		'profil'     => ['bi-person-vcard', 'Profil hráča'],
		'obr-hrac'   => ['bi-award', 'Hráč zápasu a jubileum'],
		'ulozenie'   => ['bi-cloud-check', 'Uloženie na server'],
		'editor'     => ['bi-sliders', 'Editor šablón'],
		'prvky'      => ['bi-layers', 'Prvky a ich vlastnosti'],
		'znacky'     => ['bi-braces', 'Zástupné značky'],
		'pozadie'    => ['bi-card-image', 'Pozadie a rozmer'],
		'pisma'      => ['bi-type', 'Písma'],
		'zaloha'     => ['bi-hdd', 'Záloha a prenos'],
		'galeria'    => ['bi-images', 'Galéria'],
		'instalacia' => ['bi-phone', 'Inštalácia do mobilu'],
		'tipy'       => ['bi-stars', 'Tipy a skratky'],
		'sukromie'   => ['bi-shield-check', 'Súkromie a dáta'],
		'spravca'    => ['bi-shield-lock', 'Pre správcu'],
		'problemy'   => ['bi-question-circle', 'Riešenie problémov'],
	];

	add_layout("header", array(
		"title"   => "Návod | Match Day",
		"scripts" => ["js/guide.js"],
	));
?>

<section class="hero hero-sm">
	<div class="container-xxl">
		<span class="hero-eyebrow mb-3"><i class="bi bi-book"></i> Podrobný návod</span>
		<h1 class="mb-2">Ako používať <span class="accent">Match Day</span></h1>
		<p class="lead mb-0">Od vyhľadania klubu cez úpravu šablóny až po hotový obrázok na sociálne siete – krok za krokom, s obrázkami.</p>
	</div>
</section>

<div class="container-xxl overlap">
	<div class="row g-4">

		<!-- Obsah -->
		<aside class="col-lg-3">
			<div class="card-app guide-toc-card">
				<div class="card-body p-3">
					<button class="guide-toc-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#guideToc" aria-expanded="false" aria-controls="guideToc">
						<span><i class="bi bi-list-nested me-2"></i>Obsah návodu</span><i class="bi bi-chevron-down"></i>
					</button>
					<nav class="collapse d-lg-block" id="guideToc" aria-label="Obsah návodu">
						<div class="small fw-semibold text-muted-2 text-uppercase mb-2 d-none d-lg-block">Obsah</div>
						<ul class="guide-toc">
							<?php foreach ($toc as $id => [$icon, $label]): ?>
								<li><a href="#<?= $id ?>"><i class="bi <?= $icon ?>"></i><?= plain($label) ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</div>
			</div>
		</aside>

		<!-- Obsah návodu -->
		<div class="col-lg-9">
			<article class="card-app howto">
				<div class="card-body">

<!-- ================================================================== -->
<section id="uvod">
	<h2><i class="bi bi-info-circle"></i>Čo aplikácia robí</h2>
	<p><strong>Match Day Generator</strong> vytvára hotové grafiky k futbalovým zápasom z oficiálnych údajov <strong>Futbalnet / Sportnet SFZ</strong>. Nemusíte nič prepisovať – názvy tímov, logá, dátum, čas, výsledok, strelcov, zostavy, tabuľku aj štatistiky hráčov si aplikácia načíta sama. Vy si iba vyberiete zápas a stiahnete obrázok.</p>
	<p>K dispozícii je pätnásť typov obrázkov – k <strong>klubu</strong> (Víkend klubu) a v troch skupinách – k <strong>zápasu</strong> (Match day, Zostava, Výsledok, Strelci, Súhrn, Vzájomné zápasy, Forma), k <strong>družstvu</strong> (Súpiska, Tabuľka, Kolo, Štatistiky) a k <strong>hráčovi</strong> (Profil, Hráč zápasu, Jubileum). Niekoľko ukážok:</p>
	<div class="row g-3 my-3">
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-matchday.webp', 'Ukážka obrázka Match day', '<strong>Match day</strong> – pozvánka na zápas: logá, dátum, čas výkopu, súťaž a kolo.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-zostava.webp', 'Ukážka obrázka Zostava', '<strong>Zostava</strong> – základná jedenástka, náhradníci a tréner zo zápisu o stretnutí.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-vysledok.webp', 'Ukážka obrázka Výsledok', '<strong>Výsledok</strong> – konečné skóre odohraného zápasu.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-strelci.webp', 'Ukážka obrázka Strelci', '<strong>Strelci</strong> – skóre a strelci oboch tímov s minútami.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-supiska.webp', 'Ukážka obrázka Súpiska', '<strong>Súpiska</strong> – všetci hráči družstva v sezóne s číslami dresov a trénerom.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-suhrn.webp', 'Ukážka obrázka Súhrn zápasu', '<strong>Súhrn zápasu</strong> – skóre, polčas, góly, karty, rozhodca a diváci.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-h2h.webp', 'Ukážka obrázka Vzájomné zápasy', '<strong>Vzájomné zápasy</strong> – bilancia oboch tímov, góly, karty a posledné vzájomné zápasy.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-forma.webp', 'Ukážka obrázka Forma', '<strong>Forma</strong> – posledných 5 zápasov družstva s výsledkami V / R / P.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-vikend.webp', 'Ukážka obrázka Víkend klubu', '<strong>Víkend klubu</strong> – zápasy všetkých družstiev klubu v týždni.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-tabulka.webp', 'Ukážka obrázka Tabuľka', '<strong>Tabuľka</strong> – poradie súťaže so zvýrazneným vaším družstvom.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-kolo.webp', 'Ukážka obrázka Kolo', '<strong>Kolo</strong> – program alebo výsledky všetkých zápasov kola.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-statistiky.webp', 'Ukážka obrázka Štatistiky', '<strong>Štatistiky</strong> – najlepší strelci, najviac zápasov a karty.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-profil.webp', 'Ukážka obrázka Profil hráča', '<strong>Profil hráča</strong> – fotka, číslo a štatistiky hráča v sezóne.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-hrac-zapasu.webp', 'Ukážka obrázka Hráč zápasu', '<strong>Hráč zápasu</strong> – vybraný hráč s výsledkom a jeho gólmi.', 'guide-shot-tile') ?>
		</div>
		<div class="col-6 col-md-4">
			<?= guide_shot('ukazka-jubileum.webp', 'Ukážka obrázka Jubileum', '<strong>Jubileum</strong> – napr. 100. zápas alebo 50. gól za klub.', 'guide-shot-tile') ?>
		</div>
	</div>
	<div class="guide-note">
		<i class="bi bi-info-circle-fill"></i>
		<div>Obrázky v tomto návode sú vytvorené s <strong>vymyslenými klubmi a hráčmi</strong> a so záložným písmom. Vaše obrázky budú mať skutočné údaje zápasu a vzhľad podľa vašej šablóny.</div>
	</div>
	<p class="mb-0">Všetky vaše nastavenia (rozloženie šablón, pozadia, písma) sa ukladajú <strong>iba vo vašom prehliadači</strong>. Aplikácia nepotrebuje registráciu ani prihlásenie.</p>
</section>

<!-- ================================================================== -->
<section id="rychly">
	<h2><i class="bi bi-lightning-charge"></i>Rýchly štart</h2>
	<ol class="guide-steps">
		<li>Do <strong>hľadania</strong> (na úvodnej stránke alebo hore v menu) napíšte názov svojho klubu a kliknite naň.</li>
		<li>Navrchu stránky klubu je <strong>najbližší</strong> a <strong>posledný zápas</strong> – kliknite na <strong>Match day</strong>, <strong>Zostava</strong>, <strong>Výsledok</strong> alebo <strong>Strelci</strong>.</li>
		<li>Kliknite na <strong>Stiahnuť PNG</strong> – hotovo, obrázok môžete zverejniť.</li>
	</ol>
	<p class="mb-0">Ak chcete zmeniť vzhľad (pozadie, rozmiestnenie, písmo), otvorte <a href="<?= url('nastavenia') ?>">Šablóny</a>. Stačí to urobiť raz – všetky ďalšie obrázky sa vytvoria podľa vašej šablóny.</p>
</section>

<!-- ================================================================== -->
<section id="vyhladanie">
	<h2><i class="bi bi-search"></i>1. Vyhľadanie klubu</h2>
	<p>Na úvodnej stránke je veľké pole na hľadanie. Na ostatných stránkach je rovnaké <strong>hľadanie hore v menu</strong> – kedykoľvek ho otvoríte klávesou <kbd>/</kbd> alebo <kbd>Ctrl</kbd> + <kbd>K</kbd>.</p>
	<ol class="guide-steps">
		<li>Napíšte <strong>aspoň 2 znaky</strong> názvu klubu alebo obce (napr. „<em>lúka</em>“). Zobrazia sa nájdené kluby zo Sportnetu.</li>
		<li>Kliknite na klub (alebo ho vyberte šípkami a potvrďte klávesou <kbd>Enter</kbd>). Otvorí sa stránka klubu s aktuálnou sezónou; inú sezónu vyberiete pod hľadaním alebo priamo na stránke klubu.</li>
	</ol>
	<?= guide_shot('01-vyhladanie.webp', 'Vyhľadanie klubu na úvodnej stránke', 'Po napísaní dvoch znakov sa zobrazia kluby zo Sportnetu.') ?>
	<p>Hľadanie nájde aj <strong>hráčov</strong> – všetkých registrovaných hráčov SFZ (od 3 znakov; priezvisko píšte <strong>s diakritikou</strong>, napr. „<em>Kováč</em>“, inak ho Sportnet nenájde) a hráčov a družstvá, ktoré ste si už prezreli, a stránky aplikácie (<em>galéria, šablóny, návod</em>). Kluby, ktoré ste otvorili, sú na úvodnej stránke v časti <strong>Vaše kluby</strong> a v hľadaní sa ukážu hneď po kliknutí do poľa. Krížikom klub zo zoznamu odstránite.</p>
	<div class="guide-tip">
		<i class="bi bi-lightbulb-fill"></i>
		<div>Hľadajte podľa výrazného slova z názvu klubu (obec, mesto). Diakritiku písať nemusíte, ale s ňou býva výsledok presnejší.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="zapasy">
	<h2><i class="bi bi-list-ul"></i>2. Zoznam zápasov</h2>
	<p>Stránka klubu má hore logo, názov a <strong>výber sezóny</strong>. Pod ňou sú <strong>záložky družstiev</strong> (muži, dorast, žiaci…) s počtom zápasov – kliknutím prepnete družstvo.</p>
	<?= guide_shot('02-zapasy.webp', 'Stránka klubu so zápasmi družstva', 'Navrchu najbližší a posledný zápas, pod nimi všetky zápasy družstva.') ?>
	<ul class="guide-list">
		<li><strong>Najbližší zápas</strong> – tlačidlá <strong>Match day</strong> (pozvánka) a <strong>Zostava</strong>.</li>
		<li><strong>Posledný zápas</strong> – tlačidlá <strong>Výsledok</strong>, <strong>Strelci</strong> a <strong>Zápis</strong>.</li>
		<li>V zozname má každý zápas jedno hlavné tlačidlo (<em>Match day</em> pri nadchádzajúcom, <em>Výsledok</em> pri odohratom) a tlačidlo <i class="bi bi-three-dots"></i> s ostatnými obrázkami (Zostava, Strelci, Súhrn) a zápisom o stretnutí.</li>
		<li><strong>Filter</strong> <em>Všetky / Nadchádzajúce / Odohraté</em> skryje zápasy, ktoré práve nepotrebujete. Víťaz je zvýraznený zelenou.</li>
		<li><strong>Družstvo</strong> otvorí prehľad družstva (viď <a href="#druzstvo">Prehľad družstva</a>), <strong>Súpiska</strong> vytvorí obrázok so všetkými hráčmi.</li>
		<li>Kliknutím na <strong>výsledok</strong> otvoríte celý zápis o stretnutí.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="generator">
	<h2><i class="bi bi-image"></i>3. Generovanie obrázka</h2>
	<p>Po kliknutí na typ obrázka sa otvorí <strong>generátor</strong>. Obrázok sa vytvorí priamo vo vašom prehliadači za pár sekúnd – vľavo vidíte hotový náhľad v plnej kvalite.</p>
	<?= guide_shot('03-generator.webp', 'Generátor s hotovým obrázkom Match day', 'Generátor: náhľad vľavo, ovládanie a údaje o zápase vpravo.') ?>
	<h3>Ovládanie</h3>
	<dl class="guide-dl">
		<dt><i class="bi bi-grid-3x3-gap"></i>Typ obrázka</dt>
		<dd>Prepína medzi obrázkami rovnakej skupiny bez návratu na zoznam – pri zápase <em>Match day, Zostava, Výsledok, Strelci</em> a <em>Súhrn</em>, pri družstve <em>Súpiska, Tabuľka, Kolo, Štatistiky</em>, pri hráčovi <em>Profil, Hráč zápasu, Jubileum</em>. Výsledok, strelci a súhrn sú dostupné až po odohratí zápasu.</dd>
		<dt><i class="bi bi-download"></i>Stiahnuť PNG</dt>
		<dd>Uloží obrázok do zariadenia vo formáte PNG v plnom rozlíšení. Názov súboru obsahuje typ a oba tímy, napr. <code>matchday_fk-dolna-luka_vs_….png</code>.</dd>
		<dt><i class="bi bi-clipboard"></i>Kopírovať</dt>
		<dd>Skopíruje obrázok do schránky – potom ho stačí vložiť (Ctrl + V) napríklad do príspevku na Facebooku alebo do správy.</dd>
		<dt><i class="bi bi-share"></i>Zdieľať</dt>
		<dd>Na mobile otvorí systémové zdieľanie (WhatsApp, Instagram, Messenger…). Tlačidlo sa zobrazí iba v prehliadačoch, ktoré to podporujú.</dd>
		<dt><i class="bi bi-sliders"></i>Upraviť rozloženie šablóny</dt>
		<dd>Otvorí editor šablón priamo s údajmi tohto zápasu, takže hneď vidíte, ako budú zmeny vyzerať.</dd>
	</dl>
	<p class="mb-0">V karte <strong>Údaje o zápase</strong> si môžete skontrolovať, aké údaje sa do obrázka dosadili (domáci, hostia, výsledok, dátum, súťaž, kolo).</p>
</section>

<!-- ================================================================== -->
<section id="strelci">
	<h2><i class="bi bi-list-ol"></i>Strelci gólov</h2>
	<p>Pri type <strong>Strelci</strong> si aplikácia stiahne zo Sportnetu zápis o stretnutí a vypíše strelcov oboch tímov zoradených podľa minúty. Zoznam vidíte aj vpravo pod údajmi o zápase.</p>
	<?= guide_shot('04-strelci.webp', 'Generátor s obrázkom strelcov', 'Strelci domácich vľavo, hostí vpravo. Vlastný gól je označený „(vl.)“ a pripíše sa súperovi.') ?>
	<ul class="guide-list">
		<li><strong>Vlastný gól</strong> sa započíta tímu, ktorému bol daný, a pred meno sa pridá „(vl.)“. Text aj farbu tohto označenia zmeníte v editore.</li>
		<li>Ak je gólov veľa, zoznam sa <strong>automaticky zmenší</strong>, aby sa zmestil nad nastavenú spodnú hranicu.</li>
		<li>Ak sa strelci nenačítajú (zápis ešte nie je uzavretý alebo Sportnet neodpovedá), zobrazí sa upozornenie a obrázok sa vytvorí bez nich. Skúste to neskôr znova.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="zostava">
	<h2><i class="bi bi-person-lines-fill"></i>Zostava na zápas</h2>
	<p>Obrázok <strong>Zostava</strong> zobrazí základnú jedenástku, náhradníkov a trénera jedného družstva. Údaje sa berú zo <strong>zápisu o stretnutí</strong> na Sportnete – kluby ich tam zadávajú pred zápasom, takže zostavu môžete zverejniť ešte pred výkopom.</p>
	<?= guide_shot('16-zostava.webp', 'Generátor s obrázkom zostavy', 'Zostava: hráči s číslami dresov, kapitán (C) a brankár (B). Vpravo dole je karta so zoznamom hráčov.') ?>
	<ol class="guide-steps">
		<li>V zozname zápasov kliknite pri zápase na <strong>Zostava</strong>.</li>
		<li>Predvolene sa zobrazí zostava <strong>vášho družstva</strong> (toho, ktorého zápasy prezeráte). Zostavu súpera zobrazíte prepínačom s názvami tímov v karte <strong>Zostava</strong>.</li>
		<li>Skontrolujte hráčov v karte a obrázok stiahnite alebo zdieľajte ako ostatné obrázky.</li>
	</ol>
	<ul class="guide-list">
		<li>Ak zápis nerozlišuje základ a náhradníkov (v zápisoch je to vyznačené len zriedka), do základu sa dajú prvých 11 hráčov zo zápisu a v karte <strong>Zostava</strong> sa zobrazí upozornenie – skontrolujte to cez <a href="#hraci">Upraviť hráčov</a>.</li>
		<li>Hráči v základe sú v poradí zo zápisu, brankár je prvý. Za menom kapitána je „(C)“, za brankárom „(B)“ – tieto značky zmeníte alebo vypnete v editore šablón.</li>
		<li>Ak klub ešte zostavu nezadal, aplikácia to oznámi. Zostavu môžete doplniť ručne (viď <a href="#hraci">Úprava hráčov</a>) alebo to skúsiť neskôr tlačidlom <i class="bi bi-arrow-clockwise"></i>.</li>
		<li>Zostava domácich a hostí sa v galérii ukladá ako dva samostatné obrázky.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="supiska">
	<h2><i class="bi bi-people"></i>Súpiska družstva</h2>
	<p>Obrázok <strong>Súpiska</strong> zobrazí všetkých hráčov družstva v sezóne zoradených podľa čísla dresu, spolu s trénerom. Nie je viazaný na konkrétny zápas.</p>
	<?= guide_shot('17-supiska.webp', 'Generátor s obrázkom súpisky', 'Súpiska družstva v troch stĺpcoch; pri veľkom počte hráčov sa písmo automaticky zmenší.') ?>
	<ol class="guide-steps">
		<li>Na stránke klubu kliknite v hlavičke družstva (napr. <em>Muži A</em>) na <strong>Súpiska</strong>.</li>
		<li>Aplikácia prejde zápisy všetkých odohratých zápasov družstva a zostaví z nich súpisku. Prvé načítanie môže trvať niekoľko sekúnd.</li>
		<li>V karte <strong>Súpiska</strong> vidíte počet hráčov, z koľkých zápasov sa súpiska zostavila a trénera.</li>
	</ol>
	<ul class="guide-list">
		<li>Číslo dresu je to, s ktorým hráč nastupoval najčastejšie. Kapitán je hráč, ktorý bol kapitánom najčastejšie.</li>
		<li>Na začiatku sezóny (pred prvým zápasom) Sportnet ešte nemá zápisy – súpisku vtedy vytvoríte ručne.</li>
		<li>Hráči, ktorí ešte neboli v žiadnom zápise, v súpiske nebudú – doplňte ich ručne. Hráčov, ktorí odišli, skryjete voľbou <em>Nezobraziť</em>.</li>
		<li>V editore môžete do riadku pridať aj štatistiky: <code>{apps}</code> (počet zápasov), <code>{minutes}</code> (minúty) a <code>{goals}</code> (góly), napr. <code>{nr}  {name} – {goals} g.</code></li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="hraci">
	<h2><i class="bi bi-pencil-square"></i>Úprava hráčov</h2>
	<p>Údaje na Sportnete nemusia byť vždy úplné. Pri zostave aj súpiske preto môžete hráčov upraviť tlačidlom <strong>Upraviť hráčov</strong>.</p>
	<?= guide_shot('18-upravit-hracov.webp', 'Okno Upraviť hráčov', 'Číslo, meno, zaradenie, kapitán (C) a brankár (B) pre každého hráča; dole tréner.') ?>
	<ul class="guide-list">
		<li><strong>Číslo a meno</strong> – opravte preklep alebo doplňte chýbajúce číslo dresu.</li>
		<li><strong>Zaradenie</strong> – pri zostave <em>Základ / Náhradník / Nezobraziť</em>, pri súpiske <em>Zobraziť / Nezobraziť</em>.</li>
		<li><strong>C</strong> označí kapitána (môže byť iba jeden), <strong>B</strong> brankára.</li>
		<li><strong>Pridať hráča</strong> vloží nový riadok, krížikom riadok odstránite. Riadky bez mena sa ignorujú.</li>
		<li><strong>Tréner</strong> – meno, ktoré sa dosadí do značky <code>{coach}</code>.</li>
		<li><strong>Použiť</strong> uloží úpravy a obrázok sa hneď prekreslí. V karte uvidíte „upravené ručne“.</li>
		<li><strong>Vrátiť údaje zo Sportnetu</strong> zahodí vaše úpravy.</li>
	</ul>
	<div class="guide-note">
		<i class="bi bi-info-circle-fill"></i>
		<div>Úpravy sa ukladajú <strong>iba vo vašom prehliadači</strong> – zvlášť pre každý zápas a družstvo (zostava) alebo družstvo a sezónu (súpiska). Na Sportnete sa nič nemení.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="zapis">
	<h2><i class="bi bi-file-text"></i>Detail zápasu</h2>
	<p>Na stránke klubu kliknite na <strong>výsledok</strong> (alebo čas pri nadchádzajúcom zápase), na <strong>Detail zápasu</strong> alebo v ponuke <i class="bi bi-three-dots"></i>. Funguje pre odohraté aj nadchádzajúce zápasy – pred zápasom tak hneď vidíte formu, vzájomné zápasy a tabuľku. Lišta pod hlavičkou presúva medzi časťami stránky.</p>
	<?= guide_shot('19-zapis.webp', 'Stránka detailu zápasu', 'Priebeh, štatistika vzájomných zápasov, forma, delegované osoby, štadión, zostavy, vzájomné zápasy a tabuľka – pri každej časti je tlačidlo na obrázok.') ?>
	<ul class="guide-list">
		<li><strong>Priebeh zápasu</strong> – góly <i class="bi bi-circle-fill ev-goal"></i>, žlté a červené karty a striedania <i class="bi bi-arrow-left-right"></i> s minútami; domáci vľavo, hostia vpravo. Obrázky: Výsledok, Strelci, Súhrn.</li>
		<li><strong>Štatistika</strong> – vzájomné zápasy oboch klubov v rovnakej kategórii: počet zápasov, výhry, prehry, remízy, góly a karty. Obrázok: <strong>Vzájomné zápasy</strong>.</li>
		<li><strong>Forma</strong> – posledných 5 zápasov každého tímu pred týmto zápasom (V výhra, R remíza, P prehra); prepínate tlačidlami s názvami tímov. Obrázok: <strong>Forma</strong> (pre domácich alebo hostí).</li>
		<li><strong>Delegované osoby</strong> – rozhodca, asistenti, delegát, hlavný usporiadateľ, diváci a ID zápasu. <strong>Štadión</strong> – rozmery ihriska, kapacita, majiteľ a adresa s odkazom na mapu.</li>
		<li><strong>Zostavy</strong> oboch tímov s číslami, kapitánom, brankárom a trénerom. Pri hráčovi je minúta, kedy prišiel <span class="text-success">↑</span> alebo odišiel <span class="text-danger">↓</span>; nevyužití náhradníci sú bledší. Obrázok: <strong>Zostava</strong>.</li>
		<li><strong>Vzájomné zápasy</strong> – zoznam ich posledných zápasov a <strong>Tabuľka</strong> súťaže so zvýraznenými oboma tímami (obrázok Tabuľka).</li>
	</ul>
	<div class="guide-tip">
		<i class="bi bi-lightbulb-fill"></i>
		<div><strong>Všetko je klikateľné</strong>: názov tímu otvorí prehľad družstva, meno hráča jeho profil, zápas v zozname jeho detail a odkaz <em>Klub a zápasy</em> stránku klubu. Takto sa môžete preklikávať medzi klubmi, zápasmi a hráčmi.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="druzstvo">
	<h2><i class="bi bi-bar-chart-line"></i>Prehľad družstva</h2>
	<p>Na stránke klubu kliknite v hlavičke družstva na <strong>Družstvo</strong> (alebo kdekoľvek na názov tímu). Stránka má tieto karty – pri každej je tlačidlo na obrázok:</p>
	<?= guide_shot('20-druzstvo.webp', 'Prehľad družstva', 'Karta Prehľad: miesto v tabuľke, body, bilancia, skóre, forma a rebríčky hráčov.') ?>
	<dl class="guide-dl">
		<dt><i class="bi bi-speedometer2"></i>Prehľad</dt>
		<dd>Miesto v tabuľke, body, výhry/remízy/prehry, skóre, zápasy bez inkasovaného gólu, priemerná návštevnosť, forma (posledných 5 zápasov), bilancia doma a vonku, najvyššia výhra a rebríčky – strelci, najviac odohraných minút a žlté karty.</dd>
		<dt><i class="bi bi-trophy"></i>Výsledky</dt>
		<dd>Všetky odohraté zápasy družstva v sezóne (aj pohárové), farebne výhra / remíza / prehra. Pri zápase je tlačidlo <em>Výsledok</em> a v ponuke <i class="bi bi-three-dots"></i> Strelci, Súhrn, Zostava, Vzájomné zápasy, Forma a detail zápasu.</dd>
		<dt><i class="bi bi-calendar-event"></i>Program</dt>
		<dd>Nadchádzajúce zápasy s tlačidlom <em>Match day</em> a ostatnými obrázkami v ponuke; hore obrázok programu kola.</dd>
		<dt><i class="bi bi-people"></i>Súpiska</dt>
		<dd>Brankári a hráči s číslami dresov, počtom zápasov, gólmi a minútami; kliknutím otvoríte profil hráča. Obrázok <em>Súpiska</em>.</dd>
		<dt><i class="bi bi-bar-chart-line"></i>Štatistiky hráčov</dt>
		<dd>Tabuľka všetkých hráčov: odohrané zápasy (Z), zápasy v základe (Š), minúty, góly, góly z penalty, žlté a červené karty. Kliknutím na hlavičku stĺpca zoradíte, kliknutím na meno otvoríte <a href="#profil">profil hráča</a>.</dd>
		<dt><i class="bi bi-list-ol"></i>Tabuľka</dt>
		<dd>Poradie súťaže s formou všetkých tímov; vaše družstvo je zvýraznené.</dd>
		<dt><i class="bi bi-calendar-week"></i>Kolá</dt>
		<dd>Všetky zápasy každého kola celej súťaže (aj zápasy ostatných tímov). Číslom kola prepínate, pri každom kole je tlačidlo na obrázok kola.</dd>
	</dl>
	<?= guide_shot('21-druzstvo-hraci.webp', 'Karta Hráči v prehľade družstva', 'Karta Hráči – štatistiky všetkých hráčov, dajú sa zoradiť podľa ľubovoľného stĺpca.') ?>
	<div class="guide-note">
		<i class="bi bi-info-circle-fill"></i>
		<div><strong>Minúty, zápasy a štarty</strong> sú oficiálne štatistiky Sportnetu (rovnaké ako na futbalnet.sk) – nevyužitý náhradník sa do zápasov nezapočíta. Góly a karty sa berú zo zápisov o stretnutí. <strong>Tabuľka</strong> je oficiálna tabuľka súťaže zo Sportnetu vrátane kontumácií a odpočtov bodov (dôvod odpočtu sa zobrazí pod tabuľkou); ak ju Sportnet nemá, vypočíta sa z výsledkov a stránka to oznámi. Prvé načítanie môže trvať niekoľko sekúnd, potom sa údaje hodinu uchovávajú.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="vikend">
	<h2><i class="bi bi-calendar2-week"></i>Víkend klubu</h2>
	<p>Na stránke klubu kliknite pod názvom klubu na žlté tlačidlo <strong>Víkend klubu</strong>. Vznikne jeden obrázok so zápasmi <strong>všetkých družstiev klubu</strong> (muži, dorast, žiaci…) v jednom týždni – nad každým zápasom je názov družstva.</p>
	<ul class="guide-list">
		<li>Predvolene sa zobrazí <strong>tento týždeň</strong>; ak v ňom klub nehrá, najbližší týždeň so zápasmi. Šípkami v karte <strong>Týždeň</strong> prepnete na predchádzajúci alebo nasledujúci týždeň.</li>
		<li>Nadpis sa nastaví sám: <em>PROGRAM VÍKENDU</em> (zápasy sa ešte nehrali – pri zápase je deň a čas), <em>VÝSLEDKY VÍKENDU</em> (všetko odohrané – pri zápase je skóre) alebo <em>VÍKEND KLUBU</em> (čiastočne).</li>
		<li>Prepínačom <em>Zoradiť podľa družstva</em> zoradíte zápasy od mužov po žiakov, inak sú podľa času.</li>
		<li>V galérii sa ukladá jeden obrázok na klub a týždeň – po ďalšom vygenerovaní sa prepíše.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="obr-druzstvo">
	<h2><i class="bi bi-table"></i>Obrázky Tabuľka, Kolo a Štatistiky</h2>
	<p>V prehľade družstva kliknite na <strong>Vytvoriť obrázok</strong> a vyberte typ. V generátore potom prepínate medzi <em>Súpiska, Tabuľka, Kolo</em> a <em>Štatistiky</em>.</p>
	<?= guide_shot('22-kolo.webp', 'Generátor s obrázkom kola', 'Obrázok kola: výber kola vpravo; nadpis sa sám zmení na PROGRAM KOLA alebo VÝSLEDKY KOLA.') ?>
	<ul class="guide-list">
		<li><strong>Tabuľka</strong> – aktuálne poradie s logami; vaše družstvo je zvýraznené. V editore nastavíte stĺpce (napr. pridať rozdiel skóre <code>gd</code>), farby a počet riadkov.</li>
		<li><strong>Kolo</strong> – v karte <em>Kolo</em> vyberte kolo. Nadpis sa nastaví automaticky: <em>PROGRAM KOLA</em> (ešte sa nehralo), <em>VÝSLEDKY KOLA</em> (odohrané) alebo <em>KOLO</em> (čiastočne). Prepínačom dáte zápas vášho družstva na prvé miesto.</li>
		<li><strong>Štatistiky</strong> – najlepší strelci, najviac zápasov a karty, plus bilancia družstva (miesto, body, skóre).</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="profil">
	<h2><i class="bi bi-person-vcard"></i>Profil hráča</h2>
	<p>Profil otvoríte kliknutím na meno hráča – v prehľade družstva, v zostave alebo priebehu zápasu – alebo nájdením hráča v hľadaní.</p>
	<?= guide_shot('23-profil.webp', 'Profil hráča', 'Profil: číslo, kapitán/brankár, štatistiky sezóny, všetky jeho zápasy a nahratie fotky.') ?>
	<ul class="guide-list">
		<li><strong>Štatistiky sezóny</strong> – odohrané zápasy, zápasy v základe, minúty, góly (aj z penalty), žlté a červené karty.</li>
		<li><strong>Zápasy v sezóne</strong> – súper, výsledok (farebne výhra / remíza / prehra), či hral v základe alebo striedal (s minútou), odohrané minúty, góly a karty.</li>
		<li><strong>Kariéra</strong> – súčet za všetky súťaže a sezóny zo Sportnetu (zápasy, minúty, góly, karty) a tlačidlo na jubileum podľa počtu zápasov v kariére.</li>
		<li><strong>Fotka</strong> – ak má hráč na Sportnete verejnú fotku, zobrazí sa v profile aj v obrázkoch automaticky; vlastná nahratá fotka má vždy prednosť. Pri profile je aj rok narodenia, kluby hráča a jeho posledné zápasy za všetky družstvá.</li>
		<li><strong>Fotka hráča</strong> – pretiahnite obrázok do rámčeka alebo naň kliknite. Fotka sa zmenší a uloží <strong>iba v tomto prehliadači</strong>; prenesiete ju zálohou nastavení. Najlepšie vyzerá PNG s priehľadným pozadím (napr. vyrezaný hráč v drese).</li>
		<li><strong>Obrázky hráča</strong> – tlačidlá Profil hráča, Hráč zápasu a Jubileum.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="obr-hrac">
	<h2><i class="bi bi-award"></i>Hráč zápasu a jubileum</h2>
	<?= guide_shot('24-jubileum.webp', 'Generátor s obrázkom Jubileum', 'Karta Hráč: výber hráča, fotka a pri jubileu číslo a druh jubilea.') ?>
	<ul class="guide-list">
		<li>V karte <strong>Hráč</strong> vyberiete iného hráča družstva, nahráte alebo odstránite fotku.</li>
		<li><strong>Profil hráča</strong> – fotka (bez fotky kruh s číslom dresu), meno, družstvo a štatistiky. Číslo dresu pri fotke sa zobrazí iba vtedy, keď je fotka nahratá.</li>
		<li><strong>Hráč zápasu</strong> – v karte vyberte zápas; na obrázku bude výsledok, súper (aj jeho logo) a počet gólov hráča v zápase.</li>
		<li><strong>Jubileum</strong> – predvolene počet zápasov alebo gólov v sezóne. Pri jubileu za celú kariéru (napr. <em>100. zápas za klub</em>) zadajte číslo ručne. Voľbou <em>vlastný text</em> napíšete čokoľvek, napr. „zápas v drese A-mužstva“.</li>
	</ul>
	<div class="guide-tip">
		<i class="bi bi-lightbulb-fill"></i>
		<div>V editore šablón môžete pri textoch nastaviť <strong>Zobraziť: iba keď je nahratá fotka</strong> alebo <strong>iba keď fotka chýba</strong> – napríklad veľké číslo dresu iba pri fotke.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="ulozenie">
	<h2><i class="bi bi-cloud-check"></i>Uloženie na server</h2>
	<p>Pod tlačidlami v generátore je rámček <strong>Uloženie na server</strong>. Hotový obrázok sa uloží aj na server, takže ho neskôr nájdete v <a href="<?= url('galeria') ?>">Galérii</a> a môžete naň poslať priamy odkaz.</p>
	<?= guide_shot('05-ulozenie.webp', 'Rámček Uloženie na server', 'Po uložení sa zobrazí názov súboru, jeho veľkosť a tlačidlá Otvoriť, Kopírovať odkaz a Galéria.', 'guide-shot-narrow') ?>
	<ul class="guide-list">
		<li>Prepínač <strong>Automaticky ukladať</strong> (predvolene zapnutý) uloží každý vygenerovaný obrázok sám. Ak ho vypnete, ukladáte ručne tlačidlom <strong>Uložiť</strong>. Voľba sa zapamätá.</li>
		<li>Rovnaký zápas a typ obrázka sa pri opätovnom vygenerovaní <strong>prepíše</strong> najnovšou verziou – v galérii tak nevznikajú duplikáty.</li>
		<li><strong>Kopírovať odkaz</strong> skopíruje verejnú adresu obrázka, ktorú môžete poslať komukoľvek.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="editor">
	<h2><i class="bi bi-sliders"></i>Editor šablón</h2>
	<p>V <a href="<?= url('nastavenia') ?>">Nastavení šablón</a> si upravíte vzhľad každého typu obrázka. Hore vyberiete šablónu (<em>Match day, Výsledok, Strelci</em>), v strede je plátno s náhľadom a vpravo panel s nastaveniami.</p>
	<?= guide_shot('06-editor.webp', 'Editor šablón s vybraným prvkom', 'Vybraný prvok má rámček a žltý bod ukotvenia; jeho vlastnosti sa zobrazia v paneli vpravo.') ?>
	<h3>Presúvanie prvkov</h3>
	<ol class="guide-steps">
		<li>Kliknite na prvok v náhľade (alebo v zozname <strong>Vrstvy</strong>).</li>
		<li>Potiahnite ho myšou alebo prstom na nové miesto. Pri strede a okrajoch obrázka sa zobrazia <strong>vodiace čiary</strong>, ktoré pomôžu prvok vycentrovať.</li>
		<li>Jemný posun urobíte <strong>šípkami</strong> na klávesnici (o 1 px), so <strong>Shiftom</strong> o 10 px.</li>
	</ol>
	<h3>Panel nad plátnom</h3>
	<dl class="guide-dl">
		<dt><i class="bi bi-arrow-counterclockwise"></i>Späť / Znova</dt>
		<dd>Vráti alebo zopakuje poslednú zmenu (aj klávesmi Ctrl + Z a Ctrl + Y).</dd>
		<dt><i class="bi bi-grid-3x3"></i>Mriežka a prichytávanie</dt>
		<dd>Mriežka zobrazí pomocné čiary; prichytávanie zaokrúhľuje polohu na 5, 10, 25 alebo 50 px, aby boli prvky pekne zarovnané.</dd>
		<dt><i class="bi bi-database"></i>Dáta v náhľade</dt>
		<dd><em>Ukážkové dáta</em> alebo <em>Posledný zápas</em> – ak ste prišli z generátora, uvidíte šablónu so skutočnými menami tímov a logami.</dd>
		<dt><i class="bi bi-123"></i>Počet gólov v ukážke</dt>
		<dd>Pri šablóne Strelci vyskúšate, ako vyzerá 2, 6, 12 alebo 20 gólov – užitočné pri nastavovaní automatického zmenšovania.</dd>
		<dt><i class="bi bi-download"></i>Stiahnuť náhľad</dt>
		<dd>Uloží aktuálny náhľad šablóny ako PNG.</dd>
	</dl>
	<div class="guide-note">
		<i class="bi bi-cloud-check-fill"></i>
		<div>Každá zmena sa <strong>ukladá automaticky</strong> – v hlavičke editora uvidíte „Uložené v prehliadači“. Nič netreba potvrdzovať.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="prvky">
	<h2><i class="bi bi-layers"></i>Prvky a ich vlastnosti</h2>
	<p>Karta <strong>Prvky</strong> obsahuje zoznam vrstiev. Kliknutím na vrstvu ju vyberiete, ikonou oka <i class="bi bi-eye"></i> prvok skryjete alebo znova zobrazíte. Tlačidlom <strong>Pridať</strong> vložíte nový prvok:</p>
	<div class="row g-3 my-2">
		<div class="col-sm-6"><div class="guide-card"><i class="bi bi-fonts"></i><div><strong>Text</strong><br><span>Ľubovoľný text, môže obsahovať zástupné značky (napr. názov súťaže).</span></div></div></div>
		<div class="col-sm-6"><div class="guide-card"><i class="bi bi-square-fill"></i><div><strong>Obdĺžnik (podklad)</strong><br><span>Farebná plocha pod textom – so zaoblením a priehľadnosťou.</span></div></div></div>
		<div class="col-sm-6"><div class="guide-card"><i class="bi bi-shield"></i><div><strong>Logo družstva</strong><br><span>Logo domácich alebo hostí v zvolenej veľkosti.</span></div></div></div>
		<div class="col-sm-6"><div class="guide-card"><i class="bi bi-list-ol"></i><div><strong>Zoznam strelcov</strong><br><span>Strelci domácich alebo hostí (hlavne pre šablónu Strelci).</span></div></div></div>
		<div class="col-sm-6"><div class="guide-card"><i class="bi bi-people"></i><div><strong>Zoznam hráčov</strong><br><span>Základná zostava, náhradníci alebo súpiska – v jednom až štyroch stĺpcoch.</span></div></div></div>
	</div>
	<h3>Vlastnosti textu</h3>
	<ul class="guide-list">
		<li><strong>Obsah</strong> – text so zástupnými značkami (viď nižšie), <strong>Písmo</strong>, <strong>Veľkosť</strong> a <strong>Farba</strong>.</li>
		<li><strong>Zarovnanie</strong> vľavo / na stred / vpravo – určuje, ku ktorej strane sa text drží žltého bodu ukotvenia. Pre názvy tímov a skóre použite stred.</li>
		<li><strong>Max. šírka</strong> – dlhší text sa zalomí do viacerých riadkov (0 = bez zalomenia), <strong>Riadkovanie</strong> nastaví medzeru medzi riadkami.</li>
		<li><strong>Tučné, Kurzíva, VEĽKÉ</strong> písmená, <strong>Prestrkanie</strong> (medzery medzi písmenami).</li>
		<li><strong>Obrys</strong> (hrúbka a farba), <strong>Tieň</strong> a <strong>Krytie</strong> – text bude čitateľný aj na pestrom pozadí.</li>
	</ul>
	<h3>Vlastnosti zoznamu strelcov</h3>
	<ul class="guide-list">
		<li><strong>Strelci tímu</strong> – domáci alebo hostia. Na obrázok dajte dva zoznamy: jeden pre každý tím.</li>
		<li><strong>Formát</strong> riadku, predvolene <code>{min}' {name}</code> → <em>57' Martin Kováč</em>. Môžete napísať aj <code>{name} {min}'</code>.</li>
		<li><strong>Rozostup gólov</strong> a <strong>Šírka stĺpca</strong> – vzdialenosť riadkov a maximálna šírka mena.</li>
		<li><strong>Spodná hranica (Y)</strong> a <strong>Automaticky zmenšiť</strong> – ak sa strelci nezmestia, zoznam sa zmenší (najviac na hodnotu <em>Min. zmenšenie</em>).</li>
		<li><strong>Vlastný gól</strong> – text pred menom (predvolene „(vl.) “) a voliteľne iná farba.</li>
	</ul>
	<h3>Vlastnosti zoznamu hráčov</h3>
	<ul class="guide-list">
		<li><strong>Zoznam</strong> – <em>Základná zostava</em>, <em>Náhradníci</em> alebo <em>Súpiska družstva</em>.</li>
		<li><strong>Počet stĺpcov</strong>, <strong>Šírka stĺpca</strong>, <strong>Medzera stĺpcov</strong> a <strong>Rozostup riadkov</strong>. Hráči sa plnia po stĺpcoch zhora nadol. Príliš dlhé meno sa skráti „…“.</li>
		<li><strong>Formát</strong> riadku, predvolene <code>{nr}  {name}</code>. K dispozícii sú aj <code>{apps}</code> (zápasy), <code>{starts}</code> (v základe), <code>{minutes}</code> (minúty) a <code>{goals}</code> (góly) – zmysel majú pri súpiske.</li>
		<li><strong>Kapitán</strong> a <strong>Brankár</strong> – text za menom (predvolene „ (C)“ a „ (B)“). Prázdne pole značku vypne.</li>
		<li><strong>Farba čísla</strong> – číslo dresu inou farbou (krížikom vypnete).</li>
		<li><strong>Spodná hranica</strong> a <strong>Automaticky zmenšiť</strong> – rovnako ako pri strelcoch.</li>
		<li><strong>Text, keď hráči chýbajú</strong> – napr. „Zostava bude zverejnená pred zápasom“.</li>
	</ul>
	<h3>Ďalšie prvky</h3>
	<ul class="guide-list">
		<li><strong>Fotka hráča</strong> – kruh alebo obdĺžnik s rámikom; bez fotky sa vyplní farbou a číslom dresu.</li>
		<li><strong>Tabuľka súťaže</strong> – stĺpce, výška riadku, logá, hlavička, zvýraznenie vlastného družstva, pruhovanie.</li>
		<li><strong>Zápasy kola</strong> – domáci, výsledok alebo čas, hostia; logá, farby výsledku a zvýraznenie vlastného zápasu.</li>
		<li><strong>Strelci / karty</strong> – prvok strelcov vie zobraziť aj <em>žlté a červené karty</em> (s farebnou kartičkou pred menom).</li>
		<li><strong>Zoznam hráčov</strong> vie okrem zostavy a súpisky zobraziť aj rebríčky: <em>Najlepší strelci, Najviac zápasov, Najviac kariet</em>; počet obmedzíte poľom <em>Max. hráčov</em>.</li>
	</ul>
	<p>Logo družstva môže okrem <em>Domáci</em> a <em>Hostia</em> zobraziť aj <em>Vybrané družstvo</em> (tím, ktorého zostavu alebo súpisku tvoríte) a <em>Súpera</em>.</p>
	<p class="mb-0">Vlastný prvok zmažete klávesom <kbd>Delete</kbd> alebo tlačidlom v paneli. Výber zrušíte klávesom <kbd>Esc</kbd>.</p>
</section>

<!-- ================================================================== -->
<section id="znacky">
	<h2><i class="bi bi-braces"></i>Zástupné značky</h2>
	<p>Do textu prvku môžete písať značky v zložených zátvorkách. Pri generovaní sa nahradia údajmi zápasu. Môžete ich kombinovať s vlastným textom, napr. <code>{weekday} {date} o {time}</code> → <em>Nedeľa 25.10.2026 o 14:00</em>.</p>
	<div class="table-responsive">
		<table class="table guide-table">
			<thead><tr><th>Značka</th><th>Čo sa doplní</th><th>Príklad</th></tr></thead>
			<tbody>
				<tr><td><code>{d1name}</code></td><td>názov domácich</td><td>FK Dolná Lúka</td></tr>
				<tr><td><code>{d2name}</code></td><td>názov hostí</td><td>TJ Slovan Brezina</td></tr>
				<tr><td><code>{score1}</code> / <code>{score2}</code></td><td>góly domácich / hostí</td><td>4 / 2</td></tr>
				<tr><td><code>{score}</code></td><td>celé skóre</td><td>4:2</td></tr>
				<tr><td><code>{day}</code></td><td>deň v mesiaci</td><td>25</td></tr>
				<tr><td><code>{month}</code> / <code>{monthLong}</code></td><td>mesiac skrátene / celý</td><td>Okt / Október</td></tr>
				<tr><td><code>{year}</code></td><td>rok</td><td>2026</td></tr>
				<tr><td><code>{weekday}</code></td><td>deň v týždni</td><td>Nedeľa</td></tr>
				<tr><td><code>{date}</code></td><td>celý dátum</td><td>25.10.2026</td></tr>
				<tr><td><code>{time}</code></td><td>čas výkopu</td><td>14:00</td></tr>
				<tr><td><code>{competition}</code></td><td>súťaž</td><td>VI. liga – skupina Stred</td></tr>
				<tr><td><code>{round}</code> / <code>{roundLabel}</code></td><td>kolo / kolo s textom</td><td>13 / 13. Kolo</td></tr>
				<tr><td><code>{team}</code></td><td>družstvo zostavy alebo súpisky</td><td>FK Dolná Lúka</td></tr>
				<tr><td><code>{opponent}</code></td><td>súper (zostava)</td><td>TJ Slovan Brezina</td></tr>
				<tr><td><code>{category}</code></td><td>kategória družstva (súpiska)</td><td>Muži A</td></tr>
				<tr><td><code>{season}</code></td><td>sezóna (súpiska)</td><td>2026/2027</td></tr>
				<tr><td><code>{coach}</code></td><td>tréner</td><td>Jozef Hrubý</td></tr>
				<tr><td><code>{players}</code></td><td>počet hráčov</td><td>22</td></tr>
				<tr><td><code>{halftime}</code> / <code>{spectators}</code></td><td>polčas / diváci (súhrn)</td><td>0:1 / 163</td></tr>
				<tr><td><code>{referee}</code> / <code>{venue}</code></td><td>rozhodca / štadión</td><td>Ivan Kráľ</td></tr>
				<tr><td><code>{player}</code></td><td>meno hráča (tiež <code>{playerFirst}</code>, <code>{playerLast}</code>)</td><td>Martin Kováč</td></tr>
				<tr><td><code>{playerNr}</code></td><td>číslo hráča</td><td>7</td></tr>
				<tr><td><code>{apps}</code></td><td>odohrané zápasy hráča</td><td>13</td></tr>
				<tr><td><code>{starts}</code> / <code>{minutes}</code></td><td>zápasy v základe / odohrané minúty</td><td>12 / 1064</td></tr>
				<tr><td><code>{goalsP}</code> / <code>{penalties}</code></td><td>góly hráča / z penalty</td><td>3 / 1</td></tr>
				<tr><td><code>{yellow}</code> / <code>{red}</code></td><td>žlté / červené karty</td><td>2 / 0</td></tr>
				<tr><td><code>{milestone}</code> / <code>{milestoneLabel}</code></td><td>jubileum</td><td>100 / zápas za FK…</td></tr>
				<tr><td><code>{matchHome}</code> <code>{matchScore}</code> <code>{matchAway}</code> <code>{matchGoals}</code></td><td>zápas pri obrázku Hráč zápasu</td><td>FK… 3:1 MFK… / 1</td></tr>
				<tr><td><code>{roundTitle}</code></td><td>PROGRAM KOLA / VÝSLEDKY KOLA</td><td>VÝSLEDKY KOLA</td></tr>
				<tr><td><code>{afterRound}</code></td><td>po koľkom kole je tabuľka</td><td>po 13. kole</td></tr>
				<tr><td><code>{tpos}</code> <code>{tpts}</code> <code>{tp}</code> <code>{tw}</code> <code>{td}</code> <code>{tl}</code> <code>{tgf}</code> <code>{tga}</code></td><td>miesto, body, zápasy, výhry, remízy, prehry, skóre družstva</td><td>1 · 23 · 13 · 7 · 2 · 4 · 25:15</td></tr>
			</tbody>
		</table>
	</div>
</section>

<!-- ================================================================== -->
<section id="pozadie">
	<h2><i class="bi bi-card-image"></i>Pozadie a rozmer</h2>
	<?= guide_shot('07-pozadie.webp', 'Karta Pozadie v editore', 'Vlastné pozadie nahráte pretiahnutím súboru alebo kliknutím.', 'guide-shot-narrow') ?>
	<ul class="guide-list">
		<li><strong>Nahrať vlastné pozadie</strong> – pretiahnite obrázok (PNG, JPG, WEBP) do rámčeka alebo naň kliknite. Pozadie sa uloží v prehliadači a použije sa pre vybranú šablónu.</li>
		<li><strong>Predvolené pozadie</strong> vráti pôvodné pozadie aplikácie.</li>
		<li><strong>Rozmer obrázka</strong> – pripravené rozmery pre <em>Instagram</em> (1080 × 1080, 1080 × 1350), <em>Story / Reels</em> (1080 × 1920), <em>Facebook / web</em> (1200 × 630) alebo vlastný rozmer. Tlačidlo <i class="bi bi-aspect-ratio"></i> nastaví rozmer podľa nahratého pozadia.</li>
	</ul>
	<div class="guide-tip">
		<i class="bi bi-lightbulb-fill"></i>
		<div>Pozadie pripravte v rovnakom pomere strán ako výsledný obrázok – inak sa roztiahne. Najlepšie je nahrať ho a potom kliknúť na <i class="bi bi-aspect-ratio"></i>.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="pisma">
	<h2><i class="bi bi-type"></i>Písma</h2>
	<?= guide_shot('08-pisma.webp', 'Karta Písma v editore', 'Ukážky písiem – kliknutím vyberiete písmo pre označený prvok.', 'guide-shot-narrow') ?>
	<p>Všetky obrázky aj nadpisy stránky používajú jedno písmo – <strong>Barlow Condensed</strong> (je priamo v aplikácii, nezávisí od internetu). Nadpisy sú tučnou kurzívou s červeným tieňom, podnadpisy v žltom štítku – rovnako ako na pozadiach Match day, Výsledok a Strelci. V editore pri texte nastavíte aj <em>farbu a posun tieňa, natočenie a štítok za textom</em>.</p>
	<ul class="guide-list">
		<li><strong>Písmo pre celú šablónu</strong> – vyberte písmo a kliknite na <strong>Použiť</strong>. Zmení sa všetkým textom a zoznamom strelcov. So zapnutým <em>Použiť vo všetkých šablónach</em> sa zmení aj v ostatných šablónach.</li>
		<li><strong>Ukážky písiem</strong> – desiatky voľne dostupných písiem (Google Fonts). Ak máte označený prvok, kliknutím na ukážku mu písmo hneď zmeníte.</li>
		<li><strong>Vlastné písmo</strong> – nahrajte súbor <code>.ttf</code>, <code>.otf</code>, <code>.woff</code> alebo <code>.woff2</code> (napr. klubové písmo). Uloží sa v prehliadači a objaví sa v zozname písiem.</li>
	</ul>
	<div class="guide-warn">
		<i class="bi bi-exclamation-triangle-fill"></i>
		<div>Nahrávajte iba písma, ktoré máte právo používať (napr. s licenciou OFL alebo zakúpené).</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="zaloha">
	<h2><i class="bi bi-hdd"></i>Záloha a prenos nastavení</h2>
	<p>Šablóny, pozadia a vlastné písma sú uložené <strong>iba v jednom prehliadači</strong>. Ak chcete rovnaké nastavenia na inom počítači, v mobile alebo u kolegu, použite kartu <strong>Záloha</strong>.</p>
	<?= guide_shot('09-zaloha.webp', 'Karta Záloha v editore', 'Export uloží všetko do jedného súboru .json, import ho načíta späť.', 'guide-shot-narrow') ?>
	<ol class="guide-steps">
		<li>V pôvodnom prehliadači kliknite na <strong>Exportovať všetky nastavenia</strong>. Stiahne sa súbor <code>.json</code>.</li>
		<li>Súbor preneste do druhého zariadenia (e-mail, USB, cloud…).</li>
		<li>V druhom prehliadači otvorte Šablóny → Záloha → <strong>Importovať zo súboru</strong> a vyberte súbor.</li>
	</ol>
	<p>Súčasťou zálohy je aj anonymné ID prehliadača, takže po importe uvidíte v galérii aj svoje skôr uložené obrázky.</p>
	<p class="mb-0"><strong>Obnoviť túto šablónu</strong> vráti predvolený vzhľad práve otvorenej šablóny, <strong>Obnoviť všetky šablóny</strong> vráti všetko do pôvodného stavu. Pred obnovením sa aplikácia opýta na potvrdenie.</p>
</section>

<!-- ================================================================== -->
<section id="galeria">
	<h2><i class="bi bi-images"></i>Galéria</h2>
	<p>V <a href="<?= url('galeria') ?>">Galérii</a> nájdete všetky obrázky, ktoré ste uložili na server <strong>v tomto prehliadači</strong>.</p>
	<?= guide_shot('10-galeria.webp', 'Galéria uložených obrázkov', 'Filter podľa typu, hľadanie a zoradenie; pri každom obrázku tlačidlá Stiahnuť, Odkaz a Odstrániť.') ?>
	<ul class="guide-list">
		<li><strong>Filter</strong> Všetky / Match day / Výsledok / Strelci – čísla ukazujú počet obrázkov.</li>
		<li><strong>Hľadanie</strong> podľa tímu, súťaže alebo dátumu, <strong>zoradenie</strong> podľa dátumu uloženia, dátumu zápasu alebo názvu tímu.</li>
		<li>Pri veľkom počte obrázkov sa zobrazí tlačidlo <strong>Zobraziť ďalšie</strong>.</li>
		<li><i class="bi bi-eye-slash"></i> <strong>Odstrániť z galérie</strong> skryje obrázok z vašej galérie (na serveri zostáva a odkazy naň fungujú ďalej).</li>
	</ul>
	<h3>Zväčšený náhľad</h3>
	<p>Kliknutím na obrázok ho zobrazíte cez celú obrazovku. Medzi obrázkami listujete šípkami na obrazovke, šípkami na klávesnici alebo potiahnutím prstom. Dole sú tlačidlá <strong>Stiahnuť, Zdieľať, Odkaz</strong> a otvorenie v novej karte.</p>
	<?= guide_shot('11-galeria-nahlad.webp', 'Zväčšený náhľad obrázka v galérii', 'Zväčšený náhľad s listovaním a tlačidlami na stiahnutie a zdieľanie.') ?>
</section>

<!-- ================================================================== -->
<section id="instalacia">
	<h2><i class="bi bi-phone"></i>Inštalácia do mobilu a počítača</h2>
	<p>Aplikáciu si môžete nainštalovať ako ikonu na plochu – otvorí sa v samostatnom okne bez lišty prehliadača a zapamätá si všetko ako doteraz.</p>
	<div class="row g-4 align-items-start">
		<div class="col-md-6">
			<h3 class="mt-0">Android, Chrome, Edge</h3>
			<ol class="guide-steps">
				<li>V hornej lište ťuknite na tlačidlo <strong>Nainštalovať</strong> <i class="bi bi-download"></i>.</li>
				<li>Potvrďte inštaláciu v okne prehliadača.</li>
				<li>Ikona <strong>Match Day</strong> sa objaví na ploche alebo v zozname aplikácií.</li>
			</ol>
			<h3>iPhone a iPad (Safari)</h3>
			<ol class="guide-steps">
				<li>Ťuknite na <strong>Inštalovať</strong> – zobrazí sa návod.</li>
				<li>V Safari ťuknite na <strong>Zdieľať</strong> <i class="bi bi-box-arrow-up"></i> a zvoľte <strong>Pridať na plochu</strong>.</li>
				<li>Potvrďte tlačidlom <strong>Pridať</strong>.</li>
			</ol>
			<div class="guide-note mb-0">
				<i class="bi bi-info-circle-fill"></i>
				<div>Ak tlačidlo nevidíte, aplikácia je už nainštalovaná alebo ju prehliadač nepodporuje. Inštalácia funguje iba na zabezpečenej adrese <strong>https</strong>.</div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="row g-3">
				<div class="col-6"><?= guide_shot('12-mobil.webp', 'Aplikácia na mobile s tlačidlom Inštalovať', 'Tlačidlo Inštalovať v hornej lište.', 'guide-shot-phone') ?></div>
				<div class="col-6"><?= guide_shot('13-mobil-menu.webp', 'Rozbalené menu na mobile', 'Menu sa na mobile otvára tlačidlom ☰.', 'guide-shot-phone') ?></div>
			</div>
		</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="tipy">
	<h2><i class="bi bi-stars"></i>Tipy a klávesové skratky</h2>
	<ul class="guide-list">
		<li><strong>Tmavý režim</strong> zapnete ikonou <i class="bi bi-moon-stars"></i> / <i class="bi bi-sun"></i> v hornej lište. Voľba sa zapamätá.</li>
		<li>Na dlhých stránkach sa vpravo dole zobrazí tlačidlo <i class="bi bi-arrow-up"></i> <strong>späť hore</strong>.</li>
		<li>Aplikácia funguje aj pri slabom signáli – už načítané stránky a písma sa uchovajú. Bez internetu sa zobrazí stránka „Ste offline“.</li>
	</ul>
	<div class="table-responsive">
		<table class="table guide-table">
			<thead><tr><th>Skratka (editor)</th><th>Čo urobí</th></tr></thead>
			<tbody>
				<tr><td><kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd></td><td>posun prvku o 1 px</td></tr>
				<tr><td><kbd>Shift</kbd> + šípky</td><td>posun prvku o 10 px</td></tr>
				<tr><td><kbd>Ctrl</kbd> + <kbd>Z</kbd> / <kbd>Ctrl</kbd> + <kbd>Y</kbd></td><td>späť / znova</td></tr>
				<tr><td><kbd>Delete</kbd></td><td>zmazať vlastný prvok</td></tr>
				<tr><td><kbd>Esc</kbd></td><td>zrušiť výber</td></tr>
			</tbody>
		</table>
	</div>
	<div class="table-responsive">
		<table class="table guide-table mb-0">
			<thead><tr><th>Skratka (galéria)</th><th>Čo urobí</th></tr></thead>
			<tbody>
				<tr><td><kbd>←</kbd> <kbd>→</kbd></td><td>predchádzajúci / ďalší obrázok vo zväčšenom náhľade</td></tr>
				<tr><td><kbd>Esc</kbd></td><td>zavrieť náhľad</td></tr>
			</tbody>
		</table>
	</div>
</section>

<!-- ================================================================== -->
<section id="sukromie">
	<h2><i class="bi bi-shield-check"></i>Súkromie a dáta</h2>
	<div class="row g-3">
		<div class="col-md-6"><div class="guide-card h-100"><i class="bi bi-laptop"></i><div><strong>Vo vašom prehliadači</strong><br><span>Šablóny, pozadia, vlastné písma, ručné úpravy zostáv a súpisiek, fotky hráčov, posledné hľadania, téma a náhodné anonymné ID prehliadača. Nikam sa neposielajú.</span></div></div></div>
		<div class="col-md-6"><div class="guide-card h-100"><i class="bi bi-hdd-network"></i><div><strong>Na serveri</strong><br><span>Uložené obrázky (PNG) s údajmi o zápase a záznam v logu: čo a kedy sa vytvorilo, rodina prehliadača a systému (napr. Chrome / Android) a anonymné ID.</span></div></div></div>
	</div>
	<p class="mt-3">Všetko, čo je uložené v prehliadači, vymažete odkazom <strong>Obnoviť nastavenia</strong> v pätičke stránky. Vyberiete, čo sa má zmazať (šablóny a pozadia, fotky hráčov, predvoľby, offline cache, prípadne aj ID galérie) a aplikácia sa vráti do pôvodného stavu. Predtým si môžete stiahnuť zálohu.</p>
	<p class="mt-3 mb-0">Aplikácia <strong>neukladá IP adresy</strong>, presnú verziu prehliadača, model zariadenia, jazyk, polohu ani odkiaľ ste prišli. Nepoužíva reklamné ani analytické cookies. Galéria každého prehliadača je oddelená – iní používatelia vaše obrázky v galérii nevidia (vidia ich iba ak im pošlete odkaz).</p>
</section>

<!-- ================================================================== -->
<section id="spravca">
	<h2><i class="bi bi-shield-lock"></i>Pre správcu</h2>
	<p>Správca vidí obrázky zo všetkých prehliadačov a log generovania. Prihlasuje sa na stránke <a href="<?= url('spravca') ?>"><code><?= plain(BASE_PATH) ?>/spravca</code></a> (odkaz <em>Správca</em> je aj v pätičke stránky).</p>
	<?= guide_shot('14-spravca.webp', 'Prehľad správcu', 'Prehľad správcu: počet obrázkov, obsadené miesto a odkazy na galériu všetkých a log.') ?>
	<ol class="guide-steps">
		<li>Predvolené heslo správcu je <code>asd123</code>. Na verejnom serveri ho zmeňte v súbore <code>config.local.php</code>: <code>define('GALLERY_ADMIN_KEY', 'dlhé-náhodné-heslo');</code> – kým platí predvolené heslo, prehľad správcu na to upozorní.</li>
		<li>Otvorte <strong>/spravca</strong>, zadajte heslo a kliknite na <strong>Prihlásiť</strong>. V menu pribudne položka <strong>Správca</strong>.</li>
		<li><strong>Galéria všetkých</strong> zobrazí obrázky zo všetkých prehliadačov vrátane tých, ktoré si používatelia skryli. Ikonou <i class="bi bi-trash"></i> obrázok <strong>natrvalo zmažete</strong> zo servera.</li>
		<li><strong>Log generovania</strong> zobrazí po mesiacoch, kedy a aký obrázok vznikol. Dá sa prehľadávať a stiahnuť ako TXT.</li>
		<li><strong>Kontrola Sportnetu</strong> (menu Správca) postupne overí všetkých 13 zdrojov dát na zadanom klube – sezóny, hľadanie, družstvá, zápasy, zápis, tabuľku, minúty, hráča, vzájomné zápasy – s časom odozvy. Zelená = v poriadku, žltá = zdroj odpovedá, ale klub nemá dáta (skúste iný), červená = Sportnet neodpovedá alebo zmenil formát. Tlačidlom <strong>Vymazať cache</strong> sa zahodia uložené odpovede Sportnetu a logá, takže sa načítajú nanovo.</li>
		<li><strong>Kontrola údajov zostavy</strong> – ak by Sportnet zmenil formát zápisu a zostavy by sa prestali načítavať, prihlásený správca vidí surové údaje zápisu na adrese <code>api-match?id=ID_ZÁPASU&amp;debug=1</code> (ID zápasu je v adrese generátora za <code>matchID=</code>).</li>
		<li>Po skončení sa odhláste (menu Správca → <strong>Odhlásiť</strong>).</li>
	</ol>
	<?= guide_shot('15-log.webp', 'Log generovania pre správcu', 'Log generovania – bez IP adries a osobných údajov.') ?>
	<div class="guide-warn">
		<i class="bi bi-exclamation-triangle-fill"></i>
		<div>Heslo nikdy nepíšte do adresy stránky ani ho neposielajte e-mailom. Po 5 nesprávnych pokusoch sa prihlasovanie na 10 minút zablokuje. Zmenou hesla v <code>config.local.php</code> sa všetci správcovia odhlásia.</div>
	</div>
	<h3>Inštalácia na server</h3>
	<ul class="guide-list mb-0">
		<li>PHP 8.1 alebo novšie s rozšíreniami <code>gd</code>, <code>curl</code>, <code>mbstring</code>; Apache s <code>mod_rewrite</code>.</li>
		<li>Ak aplikácia nebeží v priečinku <code>/match-day</code>, upravte <code>BASE_PATH</code> v <code>config.php</code>.</li>
		<li>Skopírujte <code>config.local.example.php</code> ako <code>config.local.php</code> a zmeňte predvolené heslo správcu (<code>asd123</code>).</li>
		<li>Priečinky <code>storage/generated</code> a <code>storage/logs</code> musia byť zapisovateľné pre PHP.</li>
		<li>Staršie logy s IP adresami a údajmi o zariadeniach sa po aktualizácii automaticky vyčistia.</li>
	</ul>
</section>

<!-- ================================================================== -->
<section id="problemy">
	<h2><i class="bi bi-question-circle"></i>Riešenie problémov</h2>
	<div class="accordion guide-faq" id="faq">
		<?php
		$faq = [
			['Tabuľka sa líši od oficiálnej', 'Aplikácia používa oficiálnu tabuľku zo Sportnetu. Iba ak ju Sportnet k súťaži nemá, vypočíta tabuľku z výsledkov – vtedy to pod tabuľkou uvidíte a kontumácie či odpočty bodov v nej nemusia byť.'],
			['Zostava nerozlišuje základ a náhradníkov', 'V zápisoch o stretnutí je náhradník vyznačený len zriedka. Ak chýba, aplikácia dá do základu prvých 11 hráčov zo zápisu a upozorní na to. Skontrolujte a upravte to tlačidlom Upraviť hráčov.'],
			['Štatistiky hráča nesedia', 'Minúty, zápasy a štarty sú oficiálne čísla Sportnetu; góly a karty sa skladajú zo zápisov o stretnutí – ak klub nezadal udalosti, v štatistike chýbajú. Ak hráč hrá aj za iné družstvo klubu, štatistiky sú iba za toto družstvo (súčet za celú kariéru je v profile hráča).'],
			['Fotka hráča sa nezobrazuje na inom zariadení', 'Fotky sa ukladajú iba v prehliadači, v ktorom ste ich nahrali. Preneste ich zálohou: Šablóny → Záloha → Exportovať a v druhom prehliadači Importovať.'],
			['Zostava alebo súpiska je prázdna či neúplná', 'Zostava sa berie zo zápisu o stretnutí – ak ho klub ešte nevyplnil, je prázdna. Súpiska sa skladá zo zápisov odohratých zápasov, preto v nej chýbajú hráči, ktorí ešte nenastúpili. V oboch prípadoch hráčov doplníte cez Upraviť hráčov. Tlačidlom so šípkou načítate údaje zo Sportnetu znova.'],
			['Nenačítajú sa sezóny alebo kluby', 'Údaje prichádzajú zo Sportnet API. Ak je dočasne nedostupné, zobrazí sa upozornenie. Obnovte stránku o chvíľu. Klub vyhľadáte až po napísaní aspoň 3 znakov.'],
			['Na obrázku chýbajú strelci', 'Zápis o stretnutí ešte nemusí byť uzavretý alebo v ňom nie sú zadaní strelci. Skúste to neskôr. Skontrolujte tiež, že šablóna Strelci obsahuje dva prvky „Zoznam strelcov“ (domáci a hostia).'],
			['Logo tímu sa nezobrazuje', 'Klub nemá na Sportnete nahraté logo, alebo sa ho nepodarilo stiahnuť. V editore skontrolujte, či prvok loga nie je skrytý (ikona oka).'],
			['Písmo vyzerá inak, ako som nastavil', 'Písma z Google Fonts potrebujú internet. Ak sa nenačítajú do 4 sekúnd, použije sa náhradné písmo. Vygenerujte obrázok znova. Vlastné písmo musí byť nahraté v tom istom prehliadači.'],
			['Na inom počítači nevidím svoje šablóny ani galériu', 'Nastavenia sú uložené iba v prehliadači. Preneste ich cez Šablóny → Záloha (export a import). Po importe sa zobrazí aj vaša galéria.'],
			['Vymazal som údaje prehliadača a všetko zmizlo', 'Vymazaním údajov stránky sa vymažú aj šablóny. Obnovte ich zo zálohy (.json). Obrázky na serveri zostali – správca ich vidí v galérii všetkých.'],
			['Kopírovanie obrázka nefunguje', 'Niektoré prehliadače (najmä Firefox a staršie Safari) kopírovanie obrázkov nepodporujú. Použite Stiahnuť PNG alebo Zdieľať.'],
			['Uloženie na server zlyhá', 'Skontrolujte pripojenie. Na serveri musí byť priečinok storage/generated zapisovateľný a nastavenie post_max_size aspoň 16 MB.'],
			['Nevidím tlačidlo Nainštalovať', 'Aplikácia je už nainštalovaná, stránka nebeží cez https, alebo prehliadač inštaláciu nepodporuje (napr. Firefox na počítači).'],
		];
		foreach ($faq as $i => [$q, $a]): ?>
			<div class="accordion-item">
				<h3 class="accordion-header">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="false" aria-controls="faq<?= $i ?>"><?= plain($q) ?></button>
				</h3>
				<div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faq">
					<div class="accordion-body"><?= plain($a) ?></div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

				</div>
			</article>
		</div>
	</div>
</div>

<!-- Zväčšený obrázok -->
<div class="modal fade" id="guideZoom" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content guide-zoom-content">
			<div class="modal-header border-0 pb-0">
				<div class="small text-muted-2" id="guideZoomCaption"></div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavrieť"></button>
			</div>
			<div class="modal-body pt-2"><img id="guideZoomImg" alt=""></div>
		</div>
	</div>
</div>

<?php add_layout("footer"); ?>
