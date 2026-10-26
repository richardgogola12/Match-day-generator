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
	<p><strong>Match Day Generator</strong> vytvára hotové grafiky k futbalovým zápasom z oficiálnych údajov <strong>Futbalnet / Sportnet SFZ</strong>. Nemusíte nič prepisovať – názvy tímov, logá, dátum, čas, výsledok aj strelcov si aplikácia načíta sama. Vy si iba vyberiete zápas a stiahnete obrázok.</p>
	<p>K dispozícii sú tri typy obrázkov:</p>
	<div class="row g-3 my-3">
		<div class="col-sm-4">
			<?= guide_shot('ukazka-matchday.webp', 'Ukážka obrázka Match day', '<strong>Match day</strong> – pozvánka na zápas: logá, dátum, čas výkopu, súťaž a kolo.', 'guide-shot-tile') ?>
		</div>
		<div class="col-sm-4">
			<?= guide_shot('ukazka-vysledok.webp', 'Ukážka obrázka Výsledok', '<strong>Výsledok</strong> – konečné skóre odohraného zápasu.', 'guide-shot-tile') ?>
		</div>
		<div class="col-sm-4">
			<?= guide_shot('ukazka-strelci.webp', 'Ukážka obrázka Strelci', '<strong>Strelci</strong> – skóre a strelci oboch tímov s minútami.', 'guide-shot-tile') ?>
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
		<li><strong>Zápasy</strong> – vyberte sezónu a začnite písať názov svojho klubu.</li>
		<li>Kliknite na <strong>Zobraziť zápasy</strong>.</li>
		<li>Pri zápase kliknite na <strong>Match day</strong>, <strong>Výsledok</strong> alebo <strong>Strelci</strong>.</li>
		<li>Kliknite na <strong>Stiahnuť PNG</strong> – hotovo, obrázok môžete zverejniť.</li>
	</ol>
	<p class="mb-0">Ak chcete zmeniť vzhľad (pozadie, rozmiestnenie, písmo), otvorte <a href="<?= url('nastavenia') ?>">Nastavenie šablón</a>. Stačí to urobiť raz – všetky ďalšie obrázky sa vytvoria podľa vašej šablóny.</p>
</section>

<!-- ================================================================== -->
<section id="vyhladanie">
	<h2><i class="bi bi-search"></i>1. Vyhľadanie klubu</h2>
	<p>Na úvodnej stránke (v menu <strong>Zápasy</strong>) nájdete formulár <em>Vyhľadať zápasy</em>.</p>
	<ol class="guide-steps">
		<li>V poli <strong>Sezóna</strong> je predvolene vybraná najnovšia sezóna. Ak potrebujete staršiu, vyberte ju zo zoznamu.</li>
		<li>Do poľa <strong>Klub / družstvo</strong> napíšte <strong>aspoň 3 znaky</strong> názvu klubu (napr. „<em>lúka</em>“). Zobrazí sa zoznam nájdených klubov – kliknite na ten správny.</li>
		<li>Kliknite na <strong>Zobraziť zápasy</strong>.</li>
	</ol>
	<?= guide_shot('01-vyhladanie.webp', 'Vyhľadanie klubu na úvodnej stránke', 'Po napísaní troch znakov sa zobrazí zoznam klubov zo Sportnetu.') ?>
	<p>Kluby, ktoré ste už hľadali, sa zobrazia v časti <strong>Naposledy hľadané</strong> pod formulárom. Kliknutím na ne sa k zápasom dostanete jedným ťuknutím. Krížikom položku odstránite, tlačidlom <em>Vymazať</em> vymažete celý zoznam.</p>
	<div class="guide-tip">
		<i class="bi bi-lightbulb-fill"></i>
		<div>Hľadajte podľa výrazného slova z názvu klubu (obec, mesto). Diakritiku písať nemusíte, ale s ňou býva výsledok presnejší.</div>
	</div>
</section>

<!-- ================================================================== -->
<section id="zapasy">
	<h2><i class="bi bi-list-ul"></i>2. Zoznam zápasov</h2>
	<p>Stránka <strong>Zápasy sezóny</strong> zobrazí všetky družstvá klubu v danej sezóne (muži, dorast, žiaci…). Ak ich je viac, hore sú tlačidlá, ktorými rýchlo skočíte na konkrétne družstvo.</p>
	<?= guide_shot('02-zapasy.webp', 'Zoznam zápasov družstva', 'Každý zápas má kolo, dátum, súťaž, stav a tlačidlá na vytvorenie obrázkov.') ?>
	<ul class="guide-list">
		<li><strong>Filter</strong> <em>Všetky / Nadchádzajúce / Odohraté</em> v hlavičke družstva skryje zápasy, ktoré práve nepotrebujete.</li>
		<li>Pri <strong>nadchádzajúcom</strong> zápase je dostupné iba tlačidlo <strong>Match day</strong> (pozvánka).</li>
		<li>Pri <strong>odohratom</strong> zápase sú navyše tlačidlá <strong>Výsledok</strong> a <strong>Strelci</strong>. Víťaz je zvýraznený tučným písmom.</li>
		<li>Zápas so stavom <em>Voľno</em> (družstvo nehrá) nemá žiadne tlačidlá.</li>
	</ul>
	<p class="mb-0">Tlačidlom <strong>Nové hľadanie</strong> v hornej časti sa vrátite na výber klubu.</p>
</section>

<!-- ================================================================== -->
<section id="generator">
	<h2><i class="bi bi-image"></i>3. Generovanie obrázka</h2>
	<p>Po kliknutí na typ obrázka sa otvorí <strong>generátor</strong>. Obrázok sa vytvorí priamo vo vašom prehliadači za pár sekúnd – vľavo vidíte hotový náhľad v plnej kvalite.</p>
	<?= guide_shot('03-generator.webp', 'Generátor s hotovým obrázkom Match day', 'Generátor: náhľad vľavo, ovládanie a údaje o zápase vpravo.') ?>
	<h3>Ovládanie</h3>
	<dl class="guide-dl">
		<dt><i class="bi bi-grid-3x3-gap"></i>Typ obrázka</dt>
		<dd>Prepína medzi <em>Match day</em>, <em>Výsledok</em> a <em>Strelci</em> bez návratu na zoznam zápasov. Výsledok a strelci sú dostupní až po odohratí zápasu.</dd>
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
		<li>V druhom prehliadači otvorte Nastavenie šablón → Záloha → <strong>Importovať zo súboru</strong> a vyberte súbor.</li>
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
		<div class="col-md-6"><div class="guide-card h-100"><i class="bi bi-laptop"></i><div><strong>Vo vašom prehliadači</strong><br><span>Šablóny, pozadia, vlastné písma, posledné hľadania, téma a náhodné anonymné ID prehliadača. Nikam sa neposielajú.</span></div></div></div>
		<div class="col-md-6"><div class="guide-card h-100"><i class="bi bi-hdd-network"></i><div><strong>Na serveri</strong><br><span>Uložené obrázky (PNG) s údajmi o zápase a záznam v logu: čo a kedy sa vytvorilo, rodina prehliadača a systému (napr. Chrome / Android) a anonymné ID.</span></div></div></div>
	</div>
	<p class="mt-3 mb-0">Aplikácia <strong>neukladá IP adresy</strong>, presnú verziu prehliadača, model zariadenia, jazyk, polohu ani odkiaľ ste prišli. Nepoužíva reklamné ani analytické cookies. Galéria každého prehliadača je oddelená – iní používatelia vaše obrázky v galérii nevidia (vidia ich iba ak im pošlete odkaz).</p>
</section>

<!-- ================================================================== -->
<section id="spravca">
	<h2><i class="bi bi-shield-lock"></i>Pre správcu</h2>
	<p>Správca vidí obrázky zo všetkých prehliadačov a log generovania. Prihlasuje sa na stránke <a href="<?= url('spravca') ?>"><code><?= plain(BASE_PATH) ?>/spravca</code></a> (odkaz <em>Správca</em> je aj v pätičke stránky).</p>
	<?= guide_shot('14-spravca.webp', 'Prehľad správcu', 'Prehľad správcu: počet obrázkov, obsadené miesto a odkazy na galériu všetkých a log.') ?>
	<ol class="guide-steps">
		<li>Na serveri v súbore <code>config.local.php</code> nastavte heslo: <code>define('GALLERY_ADMIN_KEY', 'dlhé-náhodné-heslo');</code></li>
		<li>Otvorte <strong>/spravca</strong>, zadajte heslo a kliknite na <strong>Prihlásiť</strong>. V menu pribudne položka <strong>Správca</strong>.</li>
		<li><strong>Galéria všetkých</strong> zobrazí obrázky zo všetkých prehliadačov vrátane tých, ktoré si používatelia skryli. Ikonou <i class="bi bi-trash"></i> obrázok <strong>natrvalo zmažete</strong> zo servera.</li>
		<li><strong>Log generovania</strong> zobrazí po mesiacoch, kedy a aký obrázok vznikol. Dá sa prehľadávať a stiahnuť ako TXT.</li>
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
		<li>Skopírujte <code>config.local.example.php</code> ako <code>config.local.php</code> a vyplňte heslo správcu.</li>
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
			['Nenačítajú sa sezóny alebo kluby', 'Údaje prichádzajú zo Sportnet API. Ak je dočasne nedostupné, zobrazí sa upozornenie. Obnovte stránku o chvíľu. Klub vyhľadáte až po napísaní aspoň 3 znakov.'],
			['Na obrázku chýbajú strelci', 'Zápis o stretnutí ešte nemusí byť uzavretý alebo v ňom nie sú zadaní strelci. Skúste to neskôr. Skontrolujte tiež, že šablóna Strelci obsahuje dva prvky „Zoznam strelcov“ (domáci a hostia).'],
			['Logo tímu sa nezobrazuje', 'Klub nemá na Sportnete nahraté logo, alebo sa ho nepodarilo stiahnuť. V editore skontrolujte, či prvok loga nie je skrytý (ikona oka).'],
			['Písmo vyzerá inak, ako som nastavil', 'Písma z Google Fonts potrebujú internet. Ak sa nenačítajú do 4 sekúnd, použije sa náhradné písmo. Vygenerujte obrázok znova. Vlastné písmo musí byť nahraté v tom istom prehliadači.'],
			['Na inom počítači nevidím svoje šablóny ani galériu', 'Nastavenia sú uložené iba v prehliadači. Preneste ich cez Nastavenie šablón → Záloha (export a import). Po importe sa zobrazí aj vaša galéria.'],
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
