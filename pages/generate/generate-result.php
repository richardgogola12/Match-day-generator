<?php

try {

    $slovenskeMesiace = mesiace();

    $maxTextWidth = 200; // max šírka textu v pixeloch


    // GET vstupy
    $d1name = $_GET['d1name'] ?? '';
    $d2name = $_GET['d2name'] ?? '';
    $d1url  = $_GET['d1url']  ?? '';
    $d2url  = $_GET['d2url']  ?? '';
    $startDate  = $_GET['startDate']  ?? '';
    $competitionName  = $_GET['competitionName']  ?? '';
    $round  = $_GET['round']  ?? '';
    $score1  = $_GET['score1']  ?? '';
    $score2  = $_GET['score2']  ?? '';


    // POZADIE
    $img = imagecreatefrompng(ASSETS_DIR . "img/match-day-bg.png");
    if (!$img) {
        http_response_code(500);
        exit('Chyba pozadia');
    }

    // FARBA
    $biela = imagecolorallocate($img, 255, 255, 255);

    // FONT
    $font = ASSETS_DIR . "fonts/biome-w04-regular.otf";
    $velkost = 18;

    // TEXT
    // Družstvo 1
    $lines1 = wordWrapTTFCenter($d1name, $font, $velkost, $maxTextWidth, 310); // centerX nad logom 1
    $y = 535;
    foreach ($lines1 as $line) {
        imagettftext($img, $velkost, 0, $line['x'], $y, $biela, $font, $line['text']);
        $y += $velkost + 12;
    }




    // Družstvo 2
    $lines2 = wordWrapTTFCenter($d2name, $font, $velkost, $maxTextWidth, 700); // centerX nad logom 2
    $y = 535;
    foreach ($lines2 as $line) {
        imagettftext($img, $velkost, 0, $line['x'], $y, $biela, $font, $line['text']);
        $y += $velkost + 12;
    }




    // ===== LOGÁ =====
    $logoSize = 120; // výsledná veľkosť loga

    // Družstvo 1
    if ($d1url && ($logo1 = imageFromUrl($d1url))) {
        imagecopyresampled(
            $img, $logo1,
            70, 440,        // pozícia na pozadí
            0, 0,
            $logoSize, $logoSize,
            imagesx($logo1), imagesy($logo1)
        );
        unset($logo1);
    }

    // Družstvo 2
    if ($d2url && ($logo2 = imageFromUrl($d2url))) {
        imagecopyresampled(
            $img, $logo2,
            820, 440,
            0, 0,
            $logoSize, $logoSize,
            imagesx($logo2), imagesy($logo2)
        );
        unset($logo2);
    }

    //DATUM
    $date = new DateTime($startDate);
    // Prepočet na stredoeurópsky čas (Slovensko)
    $date->setTimezone(new DateTimeZone('Europe/Bratislava'));
    // deň
    $den = $date->format('d');       // 21
    // mesiac
    $mesiac = $slovenskeMesiace[(int)$date->format('m')];
    // rok
    $rok = $date->format('Y');       // 2025
    // čas (hodiny:minúty:sekundy)
    $cas = $date->format('H:i');   // 11:30:45

    // /imagettftext($img, 170, 0, 260, 760, $biela, $font, $score1 . ":" . $score2);

    //Družstvo 1
    $vysledok = wordWrapTTFCenter($score1 . ":" . $score2, $font, 190, 800, 500); // centerX nad logom 1
    $y = 765;
    foreach ($vysledok as $line) {
        imagettftext($img, 170, 0, $line['x'], $y, $biela, $font, $line['text']);
        $y += $velkost + 12;
    }

    imagettftext($img, 80, 0, 340, 890, $biela, $font, $den);
    imagettftext($img, 30,  0, 490, 850, $biela, $font, $mesiac . ' ' . $rok);
    imagettftext($img, 30,  0, 490, 890, $biela, $font, $cas);


    //nazov udalosti
    if (obsahuje($round, "kolo")) {
        imagettftext($img, 15,  0, 60, 940, $biela, $font, $competitionName . " | " . $round);
    }else{
        imagettftext($img, 15,  0, 60, 940, $biela, $font, $competitionName . " | " . $round . ". Kolo");
    }


    // HLAVIČKY
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // OUTPUT
    imagepng($img);
    unset($img);
    exit;
} catch (Exception $e) {
     echo $e->getMessage();
}
