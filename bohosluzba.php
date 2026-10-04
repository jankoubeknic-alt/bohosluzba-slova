<?php
declare(strict_types=1);
/**
 * Generátor Bohoslužby slova: jediný soubor, vše nastavitelné hned níže.
 *
 *   Web:  bohosluzba.php                      (formulář s přepínači + výstup)
 *         bohosluzba.php?od=2026-10-05&pocet=4
 *         bohosluzba.php?den=2026-11-01       (jen jeden den)
 *   CLI:  php bohosluzba.php --od=2026-10-05 --pocet=4 --vystup=bohosluzby.html
 *
 * Přepínače lze změnit tady, nebo ve webovém formuláři / v adrese (adresa má přednost).
 */

// ======================= NASTAVENÍ =======================
$CFG = [
    // Odkud se berou texty: URL (končící /), nebo cesta k lokální kopii adresáře "misal".
    'zdroj' => getenv('BS_ZDROJ') ?: 'https://m.liturgie.cz/misal/',
    'cache' => __DIR__ . '/cache',          // kam se ukládají stažené stránky (musí být zapisovatelná)
    'cache_dni' => 90,
    'user_agent' => 'BohosluzbaSlova-generator/1.0 (osobni priprava bohosluzeb)',

    // ---- PŘEPÍNAČE ----
    'pocet' => 4,                            // kolik dalších příležitostí vypsat
    'nanebevstoupeni' => 'ctvrtek',          // 'ctvrtek' (všední den) nebo 'nedele' (přeneseno na neděli)
    'bozi_telo' => 'ctvrtek',                // 'ctvrtek' nebo 'nedele'
    'slavnosti_ve_vsedni_den' => true,       // true = nabízet i slavnosti ve všední dny; false = jen neděle
    // Které slavnosti ve všední den (měsíc-den) se nabízejí. Co nechcete, smažte.
    'svatky_ve_vsedni_den' => ['12-25', '01-01', '01-06', '03-19', '03-25', '06-24', '06-29',
                               '07-05', '08-15', '09-28', '11-01', '11-02', '12-08'],
    'vcetne_dnes' => false,                  // false = hledat od zítřka, true = od dneška
    'casove_pasmo' => 'Europe/Prague',
];
// =========================================================

// ======================= KALENDÁŘ =======================
/**
 * Římský liturgický kalendář (jen to, co potřebuje bohoslužba slova):
 * neděle a slavnosti, rok A/B/C, období, název a umístění textů na liturgie.cz.
 */

function velikonoce(int $rok): DateTimeImmutable
{
    $a = $rok % 19; $b = intdiv($rok, 100); $c = $rok % 100;
    $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $x = $h + $l - 7 * $m + 114;
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $rok, intdiv($x, 31), ($x % 31) + 1));
}

/** 1. neděle adventní daného občanského roku (neděle mezi 27. 11. a 3. 12.). */
function adventZacatek(int $rok): DateTimeImmutable
{
    $v = new DateTimeImmutable("$rok-12-24");
    return $v->modify('-' . (int)$v->format('w') . ' days')->modify('-21 days');
}

/** Liturgický rok A/B/C platný pro dané datum (od 1. neděle adventní). */
function cyklus(DateTimeImmutable $d): string
{
    $y = (int)$d->format('Y');
    if ($d < adventZacatek($y)) { $y--; }
    return ['A', 'B', 'C'][$y % 3];
}

function dnuOd(DateTimeImmutable $a, DateTimeImmutable $b): int
{
    return (int)$a->diff($b)->format('%r%a');
}

function odkaz(string $slozka, string $soubor, ?array $hledat = null): array
{
    return ['slozka' => $slozka, 'soubor' => $soubor, 'hledat' => $hledat];
}

/** Pevné slavnosti (a svátky Páně přebíjející neděli v mezidobí). */
function pevneSlavnosti(): array
{
    return [
        '01-01' => ['Matky Boží, Panny Marie', odkaz('02vanoce', '01_01.htm'), 'slavnost'],
        '01-06' => ['Zjevení Páně', odkaz('02vanoce', '01_06.htm'), 'slavnost'],
        '02-02' => ['Uvedení Páně do chrámu', odkaz('08sanctoral', '02_02.htm', ['slozky' => ['08sanctoral'], 'label' => '/Uvedení Páně/ui']), 'svatek'],
        '03-19' => ['Sv. Josefa, snoubence Panny Marie', odkaz('08sanctoral', '03_19.htm'), 'slavnost'],
        '03-25' => ['Zvěstování Páně', odkaz('08sanctoral', '03_25.htm'), 'slavnost'],
        '06-24' => ['Narození sv. Jana Křtitele', odkaz('08sanctoral', '06_24.htm'), 'slavnost'],
        '06-29' => ['Sv. Petra a Pavla, apoštolů', odkaz('08sanctoral', '06_29.htm'), 'slavnost'],
        '07-05' => ['Sv. Cyrila a Metoděje, patronů Evropy', odkaz('08sanctoral', '07_05.htm'), 'slavnost'],
        '08-06' => ['Proměnění Páně', odkaz('08sanctoral', '08_06.htm'), 'svatek'],
        '08-15' => ['Nanebevzetí Panny Marie', odkaz('08sanctoral', '08_15.htm'), 'slavnost'],
        '09-14' => ['Povýšení svatého Kříže', odkaz('08sanctoral', '09_14.htm'), 'svatek'],
        '09-28' => ['Sv. Václava, hlavního patrona českého národa', odkaz('08sanctoral', '09_28.htm'), 'slavnost'],
        '11-01' => ['Všech svatých', odkaz('08sanctoral', '11_01.htm'), 'slavnost'],
        '11-02' => ['Vzpomínka na všechny věrné zemřelé', odkaz('08sanctoral', '11_02.htm'), 'zesnuli'],
        '11-09' => ['Posvěcení lateránské baziliky', odkaz('08sanctoral', '11_09.htm'), 'svatek'],
        '12-08' => ['Neposkvrněného početí Panny Marie', odkaz('08sanctoral', '12_08.htm'), 'slavnost'],
        '12-25' => ['Narození Páně (ve dne)', odkaz('02vanoce', '12_25d.htm'), 'slavnost'],
    ];
}

function krtuPane(int $y): DateTimeImmutable
{
    $k = new DateTimeImmutable("$y-01-06");
    return $k->modify('+' . (7 - (int)$k->format('w')) . ' days');
}

function obdobiDne(DateTimeImmutable $d): string
{
    $y = (int)$d->format('Y');
    $diff = dnuOd(velikonoce($y), $d);
    if ($d >= adventZacatek($y)) { return 'advent'; }
    if ($d->format('m-d') >= '12-25' || $d <= krtuPane($y)) { return 'vanoce'; }
    if ($diff >= -46 && $diff < 0) { return 'pust'; }
    if ($diff >= 0 && $diff <= 49) { return 'velikonoce'; }
    return 'mezidobi';
}

function den(string $nazev, array $odkaz, string $hodnost, string $obdobi, string $rok, array $extra = []): array
{
    $zesnuli = $hodnost === 'zesnuli';
    return array_merge([
        'nazev' => $nazev, 'odkaz' => $odkaz, 'hodnost' => $hodnost, 'obdobi' => $obdobi, 'rok' => $rok,
        // Gloria: neděle mimo advent a půst, slavnosti, svátky.
        'gloria' => !$zesnuli && (in_array($hodnost, ['slavnost', 'svatek'], true) || !in_array($obdobi, ['advent', 'pust'], true)),
        'kredo' => !$zesnuli,
    ], $extra);
}

/** Co se slaví dané datum (jen neděle a vybrané slavnosti), nebo null. */
function popisDne(DateTimeImmutable $d, array $cfg): ?array
{
    $y = (int)$d->format('Y');
    $w = (int)$d->format('w');
    $md = $d->format('m-d');
    $e = velikonoce($y);
    $diff = dnuOd($e, $d);
    $adv = adventZacatek($y);
    $rok = cyklus($d);
    $sl = pevneSlavnosti();

    // Pohyblivé slavnosti a neděle velikonočního okruhu
    if ($diff === 0) { return den('Neděle Zmrtvýchvstání Páně', odkaz('04triduum', '05vzkriseni.htm'), 'nedele', 'velikonoce', $rok); }
    if ($diff >= 7 && $diff <= 42 && $w === 0) {
        if ($diff === 42 && $cfg['nanebevstoupeni'] === 'nedele') {
            return den('Nanebevstoupení Páně', odkaz('05velikonoce', '06_04.htm'), 'slavnost', 'velikonoce', $rok);
        }
        $n = intdiv($diff, 7) + 1;
        return den("$n. neděle velikonoční", odkaz('05velikonoce', sprintf('%02d_00.htm', $n)), 'nedele', 'velikonoce', $rok);
    }
    if ($diff === 39 && $cfg['nanebevstoupeni'] === 'ctvrtek' && in_array('nanebevstoupeni', $cfg['_vsedni'], true)) {
        return den('Nanebevstoupení Páně', odkaz('05velikonoce', '06_04.htm'), 'slavnost', 'velikonoce', $rok);
    }
    if ($diff === 49) { return den('Seslání Ducha Svatého (Letnice)', odkaz('05velikonoce', '07_07a.htm'), 'slavnost', 'velikonoce', $rok); }
    if ($diff === 56) { return den('Nejsvětější Trojice', odkaz('06mezidobi', 'NST.htm'), 'slavnost', 'mezidobi', $rok); }
    if ($diff === 63 && $cfg['bozi_telo'] === 'nedele') {
        return den('Těla a Krve Páně (Boží Tělo)', odkaz('06mezidobi', 'Tela_Krve.htm'), 'slavnost', 'mezidobi', $rok);
    }
    if ($diff === 60 && $cfg['bozi_telo'] === 'ctvrtek' && in_array('bozi_telo', $cfg['_vsedni'], true)) {
        return den('Těla a Krve Páně (Boží Tělo)', odkaz('06mezidobi', 'Tela_Krve.htm'), 'slavnost', 'mezidobi', $rok);
    }

    if ($w === 0) {
        // Předsunutá a velká období
        if ($d >= $adv && $d < new DateTimeImmutable("$y-12-25")) {
            $n = intdiv(dnuOd($adv, $d), 7) + 1;
            return den("$n. neděle adventní", odkaz('01advent', sprintf('%02d_00.htm', $n)), 'nedele', 'advent', $rok);
        }
        if ($diff >= -42 && $diff <= -14) {
            $n = intdiv($diff + 49, 7);
            return den("$n. neděle postní", odkaz('03pust', sprintf('%02d_00.htm', $n)), 'nedele', 'pust', $rok);
        }
        if ($diff === -7) { return den('Květná neděle', odkaz('03pust', '06_00.htm'), 'nedele', 'pust', $rok, ['pasije' => true]); }

        // Pevné slavnosti, které přebíjejí neděli v mezidobí a o Vánocích
        if (isset($sl[$md])) {
            [$nazev, $odk, $h] = $sl[$md];
            return den($nazev, $odk, $h, obdobiDne($d), $rok);
        }
        // Vánoce
        if ($md >= '12-26' && $md <= '12-31') { return den('Svaté Rodiny: Ježíše, Marie a Josefa', odkaz('02vanoce', 'SvRodina.htm'), 'svatek', 'vanoce', $rok); }
        if ($md >= '01-02' && $md <= '01-05') { return den('2. neděle po Narození Páně', odkaz('02vanoce', '02_00.htm'), 'nedele', 'vanoce', $rok); }
        $krtu = krtuPane($y);
        if ($d == $krtu) { return den('Křtu Páně', odkaz('02vanoce', 'KrtuPane.htm'), 'svatek', 'vanoce', $rok); }
        // Mezidobí
        if ($d == $adv->modify('-7 days')) { return den('Ježíše Krista Krále', odkaz('06mezidobi', 'Krista_Krale.htm'), 'slavnost', 'mezidobi', $rok); }
        if ($diff > 56) {
            $n = 35 - intdiv(dnuOd($d, $adv), 7);
            return den("$n. neděle v mezidobí", odkaz('06mezidobi', sprintf('%02d_00.htm', $n)), 'nedele', 'mezidobi', $rok);
        }
        if ($d > $krtu && $diff < -46) {
            $n = 2 + intdiv(dnuOd($krtu, $d) - 1, 7);
            return den("$n. neděle v mezidobí", odkaz('06mezidobi', sprintf('%02d_00.htm', $n)), 'nedele', 'mezidobi', $rok);
        }
        return null;
    }

    // Všední den: slavnost z nastavení
    if (in_array($md, $cfg['svatky_ve_vsedni_den'], true) && isset($sl[$md])) {
        [$nazev, $odk, $h] = $sl[$md];
        return den($nazev, $odk, $h, obdobiDne($d), $rok);
    }
    // Slavnost připadla na neděli adventní/postní/velikonoční → přesunuta na pondělí
    if ($w === 1) {
        $pred = $d->modify('-1 day');
        $mdp = $pred->format('m-d');
        if (isset($sl[$mdp]) && in_array($mdp, $cfg['svatky_ve_vsedni_den'], true) && $sl[$mdp][2] === 'slavnost'
            && in_array(obdobiDne($pred), ['advent', 'pust', 'velikonoce'], true)) {
            [$nazev, $odk, $h] = $sl[$mdp];
            return den($nazev . ' (přeneseno)', $odk, $h, obdobiDne($d), $rok);
        }
    }
    return null;
}

/** Dalších $pocet příležitostí počínaje datem $od (včetně). */
function dalsiPrilezitosti(DateTimeImmutable $od, int $pocet, array $cfg): array
{
    $cfg['_vsedni'] = $cfg['_vsedni'] ?? [];
    if ($cfg['nanebevstoupeni'] === 'ctvrtek') { $cfg['_vsedni'][] = 'nanebevstoupeni'; }
    if ($cfg['bozi_telo'] === 'ctvrtek') { $cfg['_vsedni'][] = 'bozi_telo'; }
    $vysl = [];
    for ($i = 0; $i < 800 && count($vysl) < $pocet; $i++) {
        $d = $od->modify("+$i days");
        $p = popisDne($d, $cfg);
        if ($p !== null) { $p['datum'] = $d; $vysl[] = $p; }
    }
    return $vysl;
}

function cesDatum(DateTimeImmutable $d): string
{
    $dny = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];
    return $dny[(int)$d->format('w')] . ' ' . $d->format('j. n. Y');
}

// ======================= TEXTY Z LITURGIE.CZ =======================
/**
 * Stažení a rozbor stránek misálu/lekcionáře z liturgie.cz (texty © ČBK, určeno pro přípravu).
 * Stránka dne obsahuje h3 = název dne, h4 = části (Vstupní modlitba, 1. čtení, Mezizpěv, ...).
 */

/** Počet znaků (UTF-8), bez mbstring. */
function delka(string $t): int
{
    return preg_match_all('~.~su', $t);
}

function stahniStranku(string $relativni, array $cfg): string
{
    $zdroj = $cfg['zdroj'];
    // Lokální kopie
    if (!preg_match('~^https?://~', $zdroj)) {
        $cesta = rtrim($zdroj, '/\\') . '/' . $relativni;
        $raw = is_file($cesta) ? file_get_contents($cesta) : false;
        if ($raw === false) { throw new RuntimeException("Soubor $cesta neexistuje."); }
        return jakoUtf8($raw);
    }
    // Cache
    @mkdir($cfg['cache'], 0775, true);
    $soubor = $cfg['cache'] . '/' . preg_replace('~[^A-Za-z0-9_.-]+~', '_', $relativni);
    if (is_file($soubor) && filemtime($soubor) > time() - $cfg['cache_dni'] * 86400) {
        return file_get_contents($soubor);
    }
    $url = rtrim($zdroj, '/') . '/' . $relativni;
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => $cfg['user_agent']]);
        $raw = curl_exec($ch);
        $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($kod >= 400) { $raw = false; }
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => 'User-Agent: ' . $cfg['user_agent'] . "\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
    }
    if ($raw === false || $raw === '') { throw new RuntimeException("Nepodařilo se stáhnout $url"); }
    $html = jakoUtf8($raw);
    @file_put_contents($soubor, $html);
    return $html;
}

function jakoUtf8(string $raw): string
{
    $cs = null;
    if (preg_match('~charset\s*=\s*["\']?([A-Za-z0-9_-]+)~i', substr($raw, 0, 3000), $m)) { $cs = strtoupper($m[1]); }
    if ($cs === null) { $cs = preg_match('//u', $raw) ? 'UTF-8' : 'WINDOWS-1250'; }
    if ($cs === 'UTF-8' || $cs === 'UTF8') { return $raw; }
    $out = function_exists('iconv') ? @iconv($cs, 'UTF-8//IGNORE', $raw) : false;
    if ($out === false && function_exists('mb_convert_encoding')) { $out = @mb_convert_encoding($raw, 'UTF-8', $cs === 'WINDOWS-1250' ? 'ISO-8859-2' : $cs); }
    return $out === false ? $raw : $out;
}

/** Najde na indexu složky odkaz podle popisku (pro stránky s neznámým názvem souboru). */
function najdiPodleNazvu(array $slozky, string $regex, array $cfg): ?array
{
    foreach ($slozky as $slozka) {
        try { $html = stahniStranku($slozka . '/index.html', $cfg); } catch (Throwable $e) { continue; }
        $dom = nactiDom($html);
        foreach ($dom->getElementsByTagName('a') as $a) {
            if (preg_match($regex, trim(preg_replace('~\s+~u', ' ', $a->textContent)))) {
                $href = basename(parse_url($a->getAttribute('href'), PHP_URL_PATH) ?? '');
                if ($href !== '') { return [$slozka, $href]; }
            }
        }
    }
    return null;
}

function nactiDom(string $html): DOMDocument
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $html = preg_replace('~<meta[^>]*charset[^>]*>~i', '', $html);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    return $dom;
}

/** Rozloží stránku na sekce: [['uroven'=>3|4,'nazev'=>..., 'radky'=>[['t'=>text,'kurziva'=>bool,'odst'=>int],...]], ...] */
function rozdelNaSekce(string $html): array
{
    $dom = nactiDom($html);
    $telo = $dom->getElementsByTagName('body')->item(0) ?? $dom->documentElement;
    $bloky = ['p', 'div', 'li', 'blockquote', 'td', 'tr', 'ul', 'ol', 'table', 'pre', 'center'];
    $sekce = [['uroven' => 0, 'nazev' => '', 'radky' => []]];
    $radek = ''; $kurz = 0; $celkem = 0; $odst = 0;
    $flush = function () use (&$sekce, &$radek, &$kurz, &$celkem, &$odst) {
        $t = trim(preg_replace('~[ \t\x{00A0}]+~u', ' ', $radek));
        if ($t !== '') {
            $sekce[count($sekce) - 1]['radky'][] = ['t' => $t, 'kurziva' => $celkem > 0 && $kurz / $celkem > 0.6, 'odst' => $odst];
        }
        $radek = ''; $kurz = 0; $celkem = 0;
    };
    $walk = function (DOMNode $n, bool $kurziva) use (&$walk, &$sekce, &$radek, &$kurz, &$celkem, &$odst, $flush, $bloky) {
        foreach ($n->childNodes as $c) {
            if ($c instanceof DOMText) {
                $t = preg_replace('~\s+~u', ' ', $c->textContent);
                if (trim($t) !== '') { $radek .= $t; $celkem += delka(trim($t)); if ($kurziva) { $kurz += delka(trim($t)); } }
                else { $radek .= ' '; }
            } elseif ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                if (in_array($tag, ['script', 'style', 'head'], true)) { continue; }
                // odkaz „zpět“ na konci stránky nepatří do textu
                if ($tag === 'a' && preg_match('~^zpět~iu', trim($c->textContent))) { continue; }
                if (preg_match('~^h([1-4])$~', $tag, $m)) {
                    $flush();
                    $sekce[] = ['uroven' => (int)$m[1], 'nazev' => trim(preg_replace('~\s+~u', ' ', $c->textContent)), 'radky' => []];
                    $odst++;
                } elseif ($tag === 'button') {
                    // rozbalovací tlačítka (např. „Čtení z Písma v roce B“) jsou nadpisy sekcí
                    $flush();
                    $sekce[] = ['uroven' => 4, 'nazev' => trim(preg_replace('~\s+~u', ' ', $c->textContent)), 'radky' => []];
                    $odst++;
                } elseif ($tag === 'br') {
                    $flush();
                } elseif (in_array($tag, $bloky, true)) {
                    $flush(); $odst++;
                    $walk($c, $kurziva);
                    $flush(); $odst++;
                } else {
                    $walk($c, $kurziva || in_array($tag, ['i', 'em'], true));
                }
            }
        }
    };
    $walk($telo, false);
    $flush();
    return $sekce;
}

function typSekce(string $nazev): ?string
{
    return match (true) {
        (bool)preg_match('~^1\.\s*čtení~iu', $nazev) => 'cteni1',
        (bool)preg_match('~^mezizpěv~iu', $nazev) => 'zalm',
        (bool)preg_match('~^2\.\s*čtení~iu', $nazev) => 'cteni2',
        (bool)preg_match('~^zpěv před evangeliem~iu', $nazev) => 'verš',
        (bool)preg_match('~^evangelium~iu', $nazev) => 'evangelium',
        default => null,
    };
}

function citaceZNadpisu(string $nazev): string
{
    return preg_match('~\s[–—-]\s(.+)$~u', $nazev, $m) ? trim($m[1]) : '';
}

function jePasazCitace(string $t): bool
{
    return delka($t) < 80 && preg_match('~\d~', $t) && !preg_match('~[.!?]$~u', rtrim($t)) && preg_match('~^[1-3]?\s?[\p{L}]{1,12}\.?\s*\d~u', $t);
}

/** Vybere sekce pro daný rok A/B/C. */
function vyberCteni(array $sekce, string $rok): array
{
    $spolecne = []; $podleRoku = []; $aktRok = null;
    foreach ($sekce as $s) {
        if (preg_match('~v roce ([ABC])\b~u', $s['nazev'], $m)) { $aktRok = $m[1]; continue; }
        $typ = typSekce($s['nazev']);
        if ($typ !== null) {
            if ($aktRok !== null) { $podleRoku[$aktRok][$typ] = $s; } else { $spolecne[$typ] = $s; }
        } elseif ($s['uroven'] <= 4 && $s['uroven'] > 0 && $aktRok !== null) {
            $aktRok = null; // konec bloku čtení (Vyznání víry, Modlitba nad dary ...)
        }
    }
    // Slavnosti nemají nadpisy „v roce A/B/C“: bloky čtení jsou za sebou v pořadí A, B, C
    if (!$podleRoku && count(array_filter($sekce, fn($s) => typSekce($s['nazev']) === 'cteni1')) > 1) {
        $blok = -1; $roky = ['A', 'B', 'C'];
        foreach ($sekce as $s) {
            $typ = typSekce($s['nazev']);
            if ($typ === 'cteni1') { $blok++; }
            if ($typ !== null && isset($roky[$blok])) { $podleRoku[$roky[$blok]][$typ] = $s; }
        }
    }
    $vysl = [];
    foreach (['cteni1', 'zalm', 'cteni2', 'verš', 'evangelium'] as $typ) {
        $vysl[$typ] = $podleRoku[$rok][$typ] ?? $spolecne[$typ] ?? null;
        if ($vysl[$typ] === null) {
            foreach ($podleRoku as $r => $p) { if (isset($p[$typ])) { $vysl[$typ] = $p[$typ]; break; } }
        }
    }
    return $vysl;
}

function sekceTextu(array $sekce, string $regex): ?string
{
    foreach ($sekce as $s) {
        if (preg_match($regex, $s['nazev'])) {
            $t = [];
            foreach ($s['radky'] as $r) { $t[] = $r['t']; }
            $txt = trim(implode(' ', $t));
            $txt = preg_replace('~\s*Amen\.?\s*$~u', '', $txt);
            return $txt !== '' ? $txt : null;
        }
    }
    return null;
}

function rozborCteni(array $s): array
{
    $radky = $s['radky'];
    $citace = citaceZNadpisu($s['nazev']);
    if ($citace === '' && $radky && jePasazCitace($radky[0]['t'])) { $citace = array_shift($radky)['t']; }
    $uvod = '';
    foreach ($radky as $i => $r) {
        if (preg_match('~^(Čtení|Slova svatého evangelia|Začátek|Pokračování|Z knihy|Z listu)~u', $r['t'])) {
            $uvod = $r['t'];
            // podtitulek (kurzívou) před úvodní větou nepatří do čteného textu
            for ($j = 0; $j <= $i; $j++) { if (isset($radky[$j]) && ($j === $i || $radky[$j]['kurziva'])) { unset($radky[$j]); } }
            break;
        }
        if ($i > 3) { break; }
    }
    $odstavce = [];
    foreach ($radky as $r) {
        if (preg_match('~^(Slovo Boží|Slovo Páně|Evangelium Páně|Zde je slovo|Slyšeli jsme)~u', $r['t'])) { continue; }
        $odstavce[$r['odst']][] = $r['t'];
    }
    $text = implode("\n\n", array_map(fn($o) => implode(' ', $o), $odstavce));
    return ['citace' => $citace, 'uvod' => $uvod, 'text' => trim($text)];
}

function rozborZalmu(array $s): array
{
    $radky = $s['radky'];
    $citace = citaceZNadpisu($s['nazev']);
    if ($radky && jePasazCitace($radky[0]['t'])) { $c = array_shift($radky)['t']; $citace = $citace ?: $c; }
    $refren = '';
    foreach ($radky as $i => $r) {
        if (preg_match('~^\[(.+)\]$~u', $r['t'], $m) || preg_match('~^(?:Odpověď|R\.)\s*:?\s*(.+)$~u', $r['t'], $m)) { $refren = trim($m[1]); unset($radky[$i]); break; }
        if ($r['kurziva']) { $refren = trim($r['t'], " []"); unset($radky[$i]); break; }
        if ($i > 2) { break; }
    }
    $sloky = [];
    foreach ($radky as $r) { $sloky[$r['odst']][] = $r['t']; }
    return ['citace' => $citace, 'refren' => $refren, 'sloky' => array_values(array_map(fn($o) => implode("\n", $o), $sloky))];
}

function rozborVerse(array $s): array
{
    $citace = citaceZNadpisu($s['nazev']);
    $radky = $s['radky'];
    if ($radky && jePasazCitace($radky[0]['t'])) { $c = array_shift($radky)['t']; $citace = $citace ?: $c; }
    $text = trim(implode(' ', array_map(fn($r) => $r['t'], $radky)));
    $bezAleluja = trim(preg_replace(['~^\s*Aleluja[.,!]?\s*~u', '~\s*Aleluja[.,!]?\s*$~u'], '', $text));
    return ['citace' => $citace, 'text' => $text, 'bez_aleluja' => $bezAleluja];
}

/** Vrátí všechny vlastní texty dne. Chybějící části zůstanou null a přidají se do 'chyby'. */
function nactiTexty(array $prilezitost, array $cfg): array
{
    $o = $prilezitost['odkaz'];
    $vysl = ['zdroj_url' => null, 'chyby' => [], 'vstupni' => null, 'po_prijimani' => null, 'cteni1' => null, 'zalm' => null, 'cteni2' => null, 'verš' => null, 'evangelium' => null];
    $slozka = $o['slozka']; $soubor = $o['soubor'];
    $html = null;
    try {
        if (!empty($o['hledat'])) { throw new RuntimeException('hledat'); }
        $html = stahniStranku("$slozka/$soubor", $cfg);
    } catch (Throwable $e) {
        $hl = $o['hledat'] ?? null;
        if ($hl) {
            $nalez = najdiPodleNazvu($hl['slozky'], $hl['label'], $cfg);
            if ($nalez) { [$slozka, $soubor] = $nalez; try { $html = stahniStranku("$slozka/$soubor", $cfg); } catch (Throwable $e2) { $vysl['chyby'][] = $e2->getMessage(); } }
            elseif (!$html) {
                try { $html = stahniStranku($o['slozka'] . '/' . $o['soubor'], $cfg); $slozka = $o['slozka']; $soubor = $o['soubor']; }
                catch (Throwable $e3) { $vysl['chyby'][] = 'Stránku dne se nepodařilo najít na liturgie.cz'; }
            }
        } else { $vysl['chyby'][] = $e->getMessage(); }
    }
    $vysl['zdroj_url'] = preg_match('~^https?://~', $cfg['zdroj']) ? rtrim($cfg['zdroj'], '/') . "/$slozka/$soubor" : "$slozka/$soubor";
    if ($html === null) { return $vysl; }

    $sekce = rozdelNaSekce($html);
    $vysl['vstupni'] = sekceTextu($sekce, '~^Vstupní modlitba~u');
    $vysl['po_prijimani'] = sekceTextu($sekce, '~^Modlitba po přijímání~u');
    $vyber = vyberCteni($sekce, $prilezitost['rok']);
    if ($vyber['cteni1']) { $vysl['cteni1'] = rozborCteni($vyber['cteni1']); }
    if ($vyber['cteni2']) { $vysl['cteni2'] = rozborCteni($vyber['cteni2']); }
    if ($vyber['zalm']) { $vysl['zalm'] = rozborZalmu($vyber['zalm']); }
    if ($vyber['verš']) { $vysl['verš'] = rozborVerse($vyber['verš']); }
    if ($vyber['evangelium']) { $vysl['evangelium'] = rozborCteni($vyber['evangelium']); }
    foreach (['vstupni' => 'vstupní modlitba', 'po_prijimani' => 'modlitba po přijímání', 'cteni1' => '1. čtení', 'zalm' => 'žalm', 'verš' => 'verš před evangeliem', 'evangelium' => 'evangelium'] as $k => $popis) {
        if (empty($vysl[$k])) { $vysl['chyby'][] = "chybí: $popis"; }
    }
    return $vysl;
}

// ======================= VÝSTUP (HTML) =======================
/** Sestavení stránky bohoslužby slova (tabulky P/L/Ž | Lid) z kalendáře a vlastních textů. */

const OTCE_NAS = 'Otče náš, jenž jsi na nebesích, posvěť se jméno tvé. Přijď království tvé. Buď vůle tvá jako v nebi, tak i na zemi. Chléb náš vezdejší dej nám dnes. A odpusť nám naše viny, jako i my odpouštíme našim viníkům. A neuveď nás v pokušení, ale zbav nás od zlého.';
const PROSTE = 'Pán Ježíš řekl: Proste, a bude vám dáno. S důvěrou v jeho slovo se pomodleme k nebeskému Otci.';
const KREDO = 'Věřím v jednoho Boha, Otce všemohoucího, Stvořitele nebe i země, všeho viditelného i neviditelného. Věřím v jednoho Pána Ježíše Krista, jednorozeného Syna Božího, který se zrodil z Otce přede všemi věky: Bůh z Boha, Světlo ze Světla, pravý Bůh z pravého Boha, zrozený, nestvořený, jedné podstaty s Otcem: skrze něho všechno je stvořeno. On pro nás lidi a pro naši spásu sestoupil z nebe. Skrze Ducha svatého přijal tělo z Marie Panny a stal se člověkem. Byl za nás ukřižován, za dnů Poncia Piláta byl umučen a pohřben. Třetího dne vstal z mrtvých podle Písma. Vstoupil do nebe, sedí po pravici Otce. A znovu přijde, ve slávě, soudit živé i mrtvé a jeho království bude bez konce. Věřím v Ducha svatého, Pána a dárce života, který z Otce i Syna vychází, s Otcem i Synem je zároveň uctíván a oslavován a mluvil ústy proroků. Věřím v jednu, svatou, všeobecnou, apoštolskou církev. Vyznávám jeden křest na odpuštění hříchů. Očekávám vzkříšení mrtvých a život budoucího věku. Amen.';
const GLORIA = 'Sláva na výsostech Bohu a na zemi pokoj lidem dobré vůle. Chválíme tě. Velebíme tě. Klaníme se ti. Oslavujeme tě. Vzdáváme ti díky pro tvou velikou slávu. Pane a Bože, nebeský Králi, Bože, Otče všemohoucí. Pane, jednorozený Synu, Ježíši Kriste. Pane a Bože, Beránku Boží, Synu Otce. Ty, který snímáš hříchy světa, smiluj se nad námi. Ty, který snímáš hříchy světa, přijmi naše prosby. Ty, který sedíš po pravici Otce, smiluj se nad námi. Neboť ty jediný jsi svatý. Ty jediný jsi Pán. Ty jediný jsi svrchovaný, Ježíši Kriste, se Svatým Duchem, ve slávě Boha Otce. Amen.';
const KAJICI = 'Vyznávám se všemohoucímu Bohu a vám všem, že často hřeším myšlením, slovy i skutky a nekonám, co mám konat: je to má vina, má veliká vina. Proto prosím Matku Boží, Pannu Marii, všechny anděly a svaté i vás, bratři a sestry, abyste se za mě u Boha přimlouvali.';
const DUCHOVNI_PRIJIMANI = 'Pane Ježíši Kriste, věřím, že jsi pravdivě a skutečně v Nejsvětější svátosti přítomen jako Bůh a člověk. Věřím, že mě miluješ. Také já chci tebe dokonale milovat, ale co mohu, Pane, bez tebe? Ty jsi, Bože, má síla. Toužím po tobě. A protože tě právě nyní nepřijímám v oltářní svátosti, prosím, přijď ke mně aspoň duchovně a učiň si u mě příbytek. Pane, zůstaň se mnou a nedopusť, abych se od tebe odloučil.';

function h(string $t): string
{
    $t = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $t = preg_replace('~\[\[(.+?)\]\]~u', '<em>$1</em>', $t);
    return nl2br($t, false);
}

function tabulka(array $radky): string
{
    $o = ['<table>', '<thead><tr><th>Předsedající, lektor, žalmista</th><th>Lid</th></tr></thead>', '<tbody>'];
    foreach ($radky as [$typ, $text]) {
        if ($typ === 'pokyn') { $o[] = '<tr class="pokyn"><td colspan="2">' . h($text) . '</td></tr>'; }
        elseif ($typ === 'Vš') { $o[] = '<tr><td></td><td><b>Vš:</b> ' . h($text) . '</td></tr>'; }
        elseif ($typ === 'Vš-kazdy') { $o[] = '<tr><td></td><td><b>Vš</b> (<em>každý přijímající</em>)<b>:</b> ' . h($text) . '</td></tr>'; }
        else { $o[] = '<tr><td><b>' . h($typ) . ':</b> ' . h($text) . '</td><td></td></tr>'; }
    }
    return implode("\n", array_merge($o, ['</tbody>', '</table>']));
}

function sekceHtml(string $nadpis, string $obsah): string
{
    return "<section>\n<h3>" . h($nadpis) . "</h3>\n$obsah\n</section>";
}

function vlastni(?string $text, string $zastupny): string
{
    return $text !== null && $text !== '' ? $text : "[[$zastupny]]";
}

/** Řádky bohoslužby slova (čtení, žalm, evangelium...) podle nalezených textů. */
function radkySlova(array $p, array $t): array
{
    $r = [];
    $cteni = function (string $klic, string $cislo, string $uvodZastupny) use (&$r, $t) {
        $c = $t[$klic] ?? null;
        $r[] = ['pokyn', "$cislo čtení." . ($c && $c['citace'] ? ' (' . $c['citace'] . ')' : '')];
        $r[] = ['L', $c && $c['uvod'] ? $c['uvod'] : $uvodZastupny];
        $r[] = ['L', vlastni($c['text'] ?? null, "VLASTNÍ TEXT - $cislo ČTENÍ.")];
        $r[] = ['L', 'Slyšeli jsme slovo Boží.'];
        $r[] = ['Vš', 'Bohu díky.'];
    };
    $cteni('cteni1', '1.', 'Čtení z ...');

    $z = $t['zalm'] ?? null;
    $r[] = ['pokyn', 'Žalm. Zpívá Vlasta nebo varhanice Lenka. Lid sedí.' . ($z && $z['citace'] ? ' (' . $z['citace'] . ')' : '')];
    if ($z && $z['refren'] && $z['sloky']) {
        $r[] = ['Ž', $z['refren']];
        $r[] = ['Vš', $z['refren']];
        foreach ($z['sloky'] as $s) { $r[] = ['Ž', $s]; $r[] = ['Vš', $z['refren']]; }
    } elseif ($z && $z['sloky']) {
        $r[] = ['Ž', implode("\n\n", $z['sloky'])];
    } else {
        $r[] = ['Ž', '[[VLASTNÍ TEXT]]'];
    }

    if (!empty($t['cteni2'])) { $cteni('cteni2', '2.', 'Čtení z ...'); }

    $v = $t['verš'] ?? null;
    if ($p['obdobi'] === 'pust') {
        $r[] = ['pokyn', 'Zpěv před evangeliem. Stojí všichni. Aleluja se v postní době nezpívá; zpívá se jen verš.'];
        $r[] = ['Ž', vlastni($v['text'] ?? null, 'VLASTNÍ TEXT - VERŠ.')];
    } else {
        $r[] = ['pokyn', 'Zpěv před evangeliem. Stojí všichni. Zpívá se. Aleluja začíná zpěvák (Ž nebo varhanice), všichni ho zopakují; verš mezi nimi zpívá nebo čte jen zpěvák (Ž). Nezpívá-li se, lze Aleluja i verš vynechat.' . ($v && $v['citace'] ? ' (' . $v['citace'] . ')' : '')];
        $r[] = ['Vš', 'Aleluja.'];
        $r[] = ['pokyn', 'Ž zpívá nebo čte verš před evangeliem, ostatní poslouchají.'];
        $r[] = ['Ž', vlastni($v['bez_aleluja'] ?? null, 'VLASTNÍ TEXT - VERŠ.')];
        $r[] = ['Vš', 'Aleluja.'];
    }

    $e = $t['evangelium'] ?? null;
    $r[] = ['pokyn', 'Evangelium. Stojí. Bez „Pán s vámi“, bez kadidla a bez kříže nad knihou.' . ($e && $e['citace'] ? ' (' . $e['citace'] . ')' : '')];
    $r[] = ['P', $e && $e['uvod'] ? $e['uvod'] : 'Slova svatého evangelia podle ...'];
    $r[] = ['Vš', 'Sláva tobě, Pane.'];
    $r[] = ['P', vlastni($e['text'] ?? null, 'VLASTNÍ TEXT - EVANGELIUM.')];
    $r[] = ['P', 'Slyšeli jsme slovo Boží.'];
    $r[] = ['Vš', 'Chvála tobě, Kriste.'];
    $r[] = ['pokyn', 'Homilie. Lid sedí. Z knížky Minutěnky, nebo z breviáře (modlitba se čtením daného dne).'];
    $r[] = ['pokyn', 'P čte kázání z knížky. – chvíle ticha –'];
    if ($p['kredo']) {
        $r[] = ['pokyn', 'Krédo. Stojí. Může se zpívat.'];
        $r[] = ['P', 'Povstaňme a vyznejme svou víru.'];
        $r[] = ['Vš', KREDO];
    }
    return $r;
}

function radkyUvod(array $p, array $t): array
{
    $r = [
        ['pokyn', 'Vš SLOKA PÍSNĚ. Vstupní zpěv. Stojí všichni.'], ['pokyn', 'Všichni se žehnají křížem.'],
        ['P', 'Ve jménu Otce i Syna i Ducha svatého.'], ['Vš', 'Amen.'],
        ['pokyn', 'Krátký pozdrav. Nikdy „Pán s vámi“.'],
        ['P', 'Milost našeho Pána Ježíše Krista, láska Boží a společenství svatého Ducha ať je s námi se všemi.'], ['Vš', 'Amen.'],
        ['pokyn', 'P přivítá společenství a krátce uvede do slavení - vlastními slovy. Krátce.'],
        ['pokyn', 'Úkon kajícnosti. Slova v závorce jen ve variantě B.'],
        ['P', 'Dříve než uslyšíme Boží slovo (a budeme přijímat eucharistii), litujme svých hříchů.'],
        ['pokyn', '– chvíle ticha –'], ['Vš', KAJICI],
        ['pokyn', 'Zakončení úkonu kajícnosti.'],
        ['P', 'Smiluj se nad námi, všemohoucí Bože, odpusť nám hříchy a doveď nás do života věčného.'], ['Vš', 'Amen.'],
        ['pokyn', 'Kyrie se má zpívat.'],
        ['P', 'Pane, smiluj se.'], ['Vš', 'Pane, smiluj se.'], ['P', 'Kriste, smiluj se.'], ['Vš', 'Kriste, smiluj se.'],
        ['P', 'Pane, smiluj se.'], ['Vš', 'Pane, smiluj se.'],
    ];
    if ($p['gloria']) {
        $r[] = ['pokyn', 'Gloria. Zpívá se, nebo říkají všichni společně.'];
        $r[] = ['Vš', GLORIA];
    }
    $r[] = ['pokyn', 'Stojí. Potom si všichni sednou.'];
    $r[] = ['P', 'Modleme se.'];
    $r[] = ['pokyn', '– chvíle ticha –'];
    $r[] = ['pokyn', 'Vstupní modlitba z misálu.'];
    $r[] = ['P', vlastni($t['vstupni'], 'VLASTNÍ TEXT.')];
    $r[] = ['Vš', 'Amen.'];
    return $r;
}

function radkyVarA(): array
{
    return [
        ['P', 'Nyní se společně pomodleme modlitbu duchovního svatého přijímání.'],
        ['pokyn', 'Místo svatého přijímání, číslo 42 v kancionálu. P stojí. Ruce P sepjaté, nikdy rozpjaté.'],
        ['Vš', DUCHOVNI_PRIJIMANI],
        ['P', PROSTE], ['Vš', OTCE_NAS],
        ['pokyn', 'Pokračuje se Závěrečnými obřady.'],
    ];
}

function radkyVarB(array $t): array
{
    return [
        ['pokyn', 'P rozloží korporál a položí purifikatorium, otevře svatostánek, poklekne, vyjme ciborium/kalich a přinese je k oltáři. Položí ciborium/kalich na oltář a zůstane na chvíli klečet před oltářem, zády k lidu. Příprava. Přisluhující mohou doprovázet se svícemi.'],
        ['pokyn', 'Vš SLOKA PÍSNĚ. Zpěv vhodně vyplní přinesení eucharistie.'], ['pokyn', 'Ruce P sepjaté, nikdy rozpjaté.'],
        ['P', PROSTE], ['Vš', OTCE_NAS],
        ['pokyn', 'Vynechává se „Vysvoboď nás …“ a modlitba „Pane Ježíši Kriste, tys řekl …“.'],
        ['P', 'Pozdravme se navzájem pozdravením pokoje.'], ['pokyn', 'Všichni se pozdraví podáním ruky.'],
        ['pokyn', 'P poklekne, vezme hostii a obrácen k lidu ji drží pozdviženou.'],
        ['P', 'Hle, Beránek Boží, ten, který na sebe vzal hříchy světa. Blahoslavení, kdo jsou pozváni k večeři Beránkově.'],
        ['Vš', 'Pane, nezasloužím si, abys ke mně přišel, ale řekni jen slovo, a má duše bude uzdravena.'],
        ['pokyn', 'Přijímání předsedajícího. P říká potichu:'], ['P', 'Tělo Kristovo ať mě zachová k životu věčnému.'],
        ['pokyn', 'P přijme hostii.'], ['pokyn', 'P vezme ciborium, přistoupí k přijímajícím a každému ukáže hostii. Podává P.'],
        ['P', 'Tělo Kristovo.'], ['Vš-kazdy', 'Amen.'],
        ['pokyn', 'Vš zpívají sloku písně k přijímání (pokud to čas umožňuje). Zpěv začne, když přijímá předsedající. Není-li zpěv, lze přečíst antifonu z misálu.'],
        ['pokyn', 'P setře zbytky z patény do ciboria, případně si omyje ruce, uloží ciborium do svatostánku a poklekne. Pak se vrátí a může si sednout. Lid zpívá.'],
        ['pokyn', 'Ruce P sepjaté.'], ['P', 'Modleme se.'], ['pokyn', '– chvíle ticha –'],
        ['P', vlastni($t['po_prijimani'], 'VLASTNÍ TEXT MODLITBY PO PŘIJÍMÁNÍ.')], ['Vš', 'Amen.'],
        ['pokyn', 'Poté pokračujte Závěrečnými obřady.'],
    ];
}

function radkyZaver(): array
{
    return [
        ['pokyn', 'P krátká sdělení (jen je-li třeba, podle pokynů duchovního správce).'],
        ['pokyn', 'P se sám žehná křížem a říká: Stojí. Předsedající nežehná lidu a nedělá žádné gesto nad lidem.'],
        ['P', 'Dej nám, Bože, své požehnání, chraň nás všeho zlého a doveď nás do života věčného.'], ['Vš', 'Amen.'],
        ['pokyn', 'Ruce P sepjaté, obrácen k lidu.'], ['P', 'Dobrořečme Pánu.'], ['Vš', 'Bohu díky.'],
        ['pokyn', 'Vš zpívají sloku písně. Závěrečný zpěv.'], ['pokyn', 'P pozdraví oltář úklonou a odchází.'],
    ];
}

/** Jedna bohoslužba (bez <html> obalu). */
function bohosluzbaHtml(array $p, array $t, bool $zalomit): string
{
    $hlava = '<header' . ($zalomit ? ' class="nova-strana"' : '') . '><h2>' . h($p['nazev']) . '</h2>'
        . '<p class="datum">' . h(cesDatum($p['datum'])) . ' · liturgický rok ' . h($p['rok']) . '</p>';
    $hlava .= '<p class="zdroj">Zdroj textů: <a href="' . h((string)$t['zdroj_url']) . '">' . h((string)$t['zdroj_url']) . '</a> (Český misál a Mešní lekcionář, © ČBK; vybráno podle roku ' . h($p['rok']) . ').</p>';
    if ($t['chyby']) {
        $hlava .= '<p class="chyba"><b>Pozor:</b> ' . h(implode('; ', $t['chyby'])) . '. Doplňte ručně nebo zkontrolujte stránku na liturgie.cz.</p>';
    }
    if ($p['hodnost'] === 'zesnuli') {
        $hlava .= '<p class="chyba"><b>Pozor:</b> vzpomínka na zemřelé má vlastní formulář; ověřte, že je tento rozpis pro vaše slavení vhodný.</p>';
    }
    $hlava .= '</header>';
    $out = [$hlava];
    $out[] = sekceHtml('Úvodní obřady', tabulka(radkyUvod($p, $t)));
    $out[] = sekceHtml('Bohoslužba slova', tabulka(radkySlova($p, $t)));
    $out[] = sekceHtml('Přímluvy z modré knížky', '<p><em>VLASTNÍ TEXT PŘÍMLUV.</em></p>');
    $out[] = sekceHtml('Varianta A: bez Vlasty a Honzy (duchovní přijímání)', tabulka(radkyVarA()));
    $out[] = sekceHtml('Varianta B: když vede Vlasta nebo Honza (se svatým přijímáním)', tabulka(radkyVarB($t)));
    $out[] = sekceHtml('Závěrečné obřady (v obou variantách)', tabulka(radkyZaver()));
    return implode("\n", $out);
}

const CSS = <<<'CSS'
@page { size: A4; margin: 14mm; }
* { box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; line-height: 1.3; color: #000; background: #fff; max-width: 190mm; margin: 0 auto; padding: 8mm; }
h1 { font-size: 18pt; margin: 0 0 6pt; }
h2 { font-size: 15pt; margin: 0 0 3pt; }
h3 { font-size: 13pt; margin: 14pt 0 5pt; break-after: avoid; }
p { margin: 0 0 6pt; }
.datum { font-weight: bold; }
.zdroj, .legenda { font-size: 9.5pt; }
.chyba { border: 1px solid #000; padding: 3pt 5pt; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: 1px solid #000; padding: 3pt 5pt; vertical-align: top; width: 50%; }
th { text-align: left; }
tr { break-inside: avoid; }
tr.pokyn td { text-align: center; font-style: italic; }
.nova-strana { break-before: page; }
form.nastaveni { border: 1px solid #000; padding: 4pt 6pt; margin: 0 0 8pt; font-size: 10pt; }
nav ol { margin: 0 0 8pt; padding-left: 16pt; }
form.nastaveni .radek { margin: 3pt 0; }
.krabice { border: 1px solid #000; padding: 6pt 8pt; margin: 8pt 0; font-size: 10pt; }
.krabice h2 { font-size: 12pt; margin: 0 0 4pt; }
@media print { form.nastaveni { display: none; } .nezobrazovat { display: none; } body { padding: 0; max-width: none; } a { color: #000; text-decoration: none; } }
CSS;

function stranka(array $prilezitosti, array $cfg, bool $web = false, ?array $upravy = null, ?string $formular = null): string
{
    $casti = []; $obsah = []; $odkazy = []; $maDruheCteni = false;
    foreach ($prilezitosti as $i => $p) {
        $t = uplatniUpravy(nactiTexty($p, $cfg), $upravy['texty'] ?? []);
        $maDruheCteni = !empty($t['cteni2']);
        $casti[] = bohosluzbaHtml($p, $t, $i > 0);
        $obsah[] = '<li>' . h(cesDatum($p['datum'])) . ': ' . h($p['nazev']) . '</li>';
        $popisek = h(cesDatum($p['datum'])) . ': ' . h($p['nazev']);
        $zdroj = '<a href="' . h((string)$t['zdroj_url']) . '">' . h((string)$t['zdroj_url']) . '</a>';
        $kontrola = $web ? ' – <a href="?uprava=' . h($p['datum']->format('Y-m-d')) . '">kontrola textů</a>' : '';
        $odkazy[] = '<p>' . $popisek . $kontrola . '<br>zdroj: ' . $zdroj . '</p>';
        if (!empty($p['pasije'])) { $odkazy[] = '<p>' . $popisek . ', pašije (evangelium v ročním cyklu A/B/C): ' . $zdroj . '</p>'; }
    }
    $zakladni = preg_match('~^https?://~', $cfg['zdroj']) ? rtrim($cfg['zdroj'], '/') . '/' : 'https://m.liturgie.cz/misal/';
    $odkazy[] = '<p>Misál (rejstřík všech stránek): <a href="' . h($zakladni) . '">' . h($zakladni) . '</a></p>';
    $odkazy[] = '<p>Kalendář misálu (rozpis dní a slavností): <a href="' . h($zakladni . 'kalendar.htm') . '">' . h($zakladni . 'kalendar.htm') . '</a></p>';
    $od = $prilezitosti ? $prilezitosti[0]['datum']->format('j. n. Y') : '';

    // Stav kontroly: tisk až po zaškrtnutí všech částí (jen po odeslání kontroly)
    $tisk = false; $stav = '';
    if ($upravy !== null) {
        $nazvy = castyUpravy();
        $ruc = array_map(fn($k) => $nazvy[$k][0], array_keys($upravy['texty']));
        $nove = array_map(fn($k) => $nazvy[$k][0], array_diff(array_keys($nazvy), array_keys($upravy['overeno']), $maDruheCteni ? [] : ['cteni2']));
        $tisk = $nove === [];
        $stav = '<p><b>Kontrola:</b> ručně upraveno: ' . h($ruc ? implode(', ', $ruc) : 'nic') . '. Neověřeno (nezaškrtnuto): ' . h($nove ? implode(', ', $nove) : 'nic') . '.</p>';
    }
    $krabice = '<div class="krabice nezobrazovat"><h2>Kontrola textů</h2>'
        . ($web ? '<p><b>Tisk bude dostupný až po kontrole textů.</b> Porovnejte každou část se zdrojem a potom vytvořte bohoslužbu s vlastními texty tlačítkem na stránce kontroly.</p>' : '')
        . implode('', $odkazy)
        . $stav
        . ($web ? '<p><button type="button" onclick="window.print()"' . ($tisk ? '' : ' disabled') . '>Tisk / PDF</button>' . ($tisk ? '' : ' (zpřístupní se po zaškrtnutí všech částí)') . '</p>' : '')
        . '</div>';
    $uvod = '<h1>Bohoslužba slova</h1>' . ($formular ?? '') . $krabice . '<nav><ol>' . implode('', $obsah) . '</ol></nav>'
        . '<p class="legenda"><b>Zkratky:</b> <b>P</b> = předsedající (Vlasta nebo Honza, jsou-li přítomni, jinak jiný pověřený), <b>Ž</b> = žalmista (Vlasta nebo varhanice Lenka), <b>L</b> = lektor, <b>Vš</b> = všichni. Kurzívou jsou řádky, které se nečtou nahlas (pokyny a poznámky). Varianta A platí, když nejsou přítomni ani Vlasta, ani Honza; varianta B, když vede Vlasta nebo Honza.</p>';
    return "<!doctype html>\n<html lang=\"cs\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Bohoslužba slova od $od</title>\n<style>" . CSS . "</style>\n</head>\n<body>\n$uvod\n" . implode("\n", $casti) . "\n</body>\n</html>\n";
}

// ======================= KONTROLA A VLASTNÍ TEXTY =======================
/**
 * Kontrola textů: uživatel porovná automatický text se zdrojem a případně napíše vlastní.
 * Na server se nic neukládá. Vlastní texty jdou formulářem přímo do vytvoření bohoslužby.
 */
const UPRAVA_CSS = <<<'CSS'
.cast { border: 1px solid #999; padding: 6pt 8pt; margin: 8pt 0; }
.cast h3 { margin-top: 0; }
.cast label { display: block; margin: 4pt 0; }
.cast textarea { width: 100%; font: inherit; font-size: 10.5pt; }
.auto { background: #f4f4f4; padding: 4pt 6pt; font-size: 10pt; margin-bottom: 4pt; }
button { font: inherit; padding: 4pt 10pt; }
@media print { .nezobrazovat { display: none; } }
CSS;

/** Části bohoslužby, které lze zkontrolovat a přepsat: klíč => [nadpis, popis v hlášce „chybí“]. */
function castyUpravy(): array
{
    return [
        'vstupni' => ['Vstupní modlitba', 'vstupní modlitba'],
        'cteni1' => ['1. čtení', '1. čtení'],
        'zalm' => ['Žalm', 'žalm'],
        'cteni2' => ['2. čtení (jen dny s druhým čtením)', null],
        'verš' => ['Zpěv před evangeliem (verš bez Aleluja)', 'verš před evangeliem'],
        'evangelium' => ['Evangelium', 'evangelium'],
        'po_prijimani' => ['Modlitba po přijímání', 'modlitba po přijímání'],
    ];
}

/** Automatický text části tak, jak ho nactiTexty vrátí (pro porovnání se zdrojem). */
function automatickyText(string $klic, array $t): string
{
    return match ($klic) {
        'vstupni', 'po_prijimani' => $t[$klic] ?? '',
        'cteni1', 'cteni2', 'evangelium' => $t[$klic]['text'] ?? '',
        'zalm' => trim(($t['zalm']['refren'] ?? '') . "\n\n" . implode("\n\n", $t['zalm']['sloky'] ?? [])),
        'verš' => $t['verš']['bez_aleluja'] ?? '',
        default => '',
    };
}

/** Přepíše automatické části vlastními texty (jen v paměti, pro tuto odpověď). Zalm: první odstavec je refrén, další jsou sloky. */
function uplatniUpravy(array $t, array $texty): array
{
    foreach ($texty as $klic => $text) {
        switch ($klic) {
            case 'vstupni':
            case 'po_prijimani':
                $t[$klic] = $text;
                break;
            case 'cteni1':
            case 'cteni2':
            case 'evangelium':
                $t[$klic] = array_merge(['citace' => '', 'uvod' => ''], $t[$klic] ?? [], ['text' => $text]);
                break;
            case 'verš':
                $t['verš'] = array_merge(['citace' => ''], $t['verš'] ?? [], ['text' => $text, 'bez_aleluja' => $text]);
                break;
            case 'zalm':
                $casti = array_values(array_filter(array_map('trim', preg_split('~\n\s*\n~', $text)), fn($c) => $c !== ''));
                $refren = array_shift($casti) ?? '';
                $t['zalm'] = array_merge(['citace' => ''], $t['zalm'] ?? [], ['refren' => $refren, 'sloky' => $casti]);
                break;
        }
        $popis = castyUpravy()[$klic][1] ?? null;
        if ($popis !== null) { $t['chyby'] = array_values(array_filter($t['chyby'] ?? [], fn($c) => $c !== "chybí: $popis")); }
    }
    return $t;
}

/** Vytáhne z odeslaného formuláře vlastní texty (prázdné se neberou) a označení „sedí“. */
function zFormulare(array $post): array
{
    $ov = is_array($post['ov'] ?? null) ? $post['ov'] : [];
    $ok = is_array($post['ok'] ?? null) ? $post['ok'] : [];
    $texty = []; $overeno = [];
    foreach (array_keys(castyUpravy()) as $klic) {
        $text = trim(str_replace("\r\n", "\n", (string)($ov[$klic] ?? '')));
        if ($text !== '') { $texty[$klic] = $text; }
        if (!empty($ok[$klic])) { $overeno[$klic] = true; }
    }
    return ['texty' => $texty, 'overeno' => $overeno];
}

/** Stránka ke kontrole jednoho dne: automatický text, políčko pro vlastní text a „sedí“. */
function upravaStranka(DateTimeImmutable $d, array $cfg): string
{
    $datum = $d->format('Y-m-d');
    $p = popisDne($d, $cfg + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]);
    if ($p === null) { return '<!doctype html><meta charset="utf-8"><p>Tento den není nedělí ani nastavenou slavností.</p>'; }
    $p['datum'] = $d;
    $t = nactiTexty($p, $cfg);
    $casti = '';
    foreach (castyUpravy() as $klic => [$nadpis]) {
        $auto = automatickyText($klic, $t);
        $casti .= '<section class="cast"><h3>' . h($nadpis) . '</h3>'
            . '<div class="auto"><b>Automatický text:</b><br>' . ($auto !== '' ? h($auto) : '<em>nenalezen</em>') . '</div>'
            . '<label>Vlastní text (prázdné = použije se automatický):<textarea name="ov[' . $klic . ']" rows="' . ($klic === 'zalm' ? 8 : 5) . '"></textarea></label>'
            . '<label><input type="checkbox" name="ok[' . $klic . ']" value="1"> Sedí, text jsem zkontroloval</label></section>';
    }
    return "<!doctype html>\n<html lang=\"cs\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Kontrola textů $datum</title>\n<style>" . CSS . UPRAVA_CSS . "</style>\n</head>\n<body>\n"
        . '<h1>Kontrola textů: ' . h(cesDatum($d)) . '</h1><p class="datum">' . h($p['nazev']) . ' · liturgický rok ' . h($p['rok']) . '</p>'
        . '<p class="zdroj">Zdroj: <a href="' . h((string)$t['zdroj_url']) . '" target="_blank" rel="noopener">' . h((string)$t['zdroj_url']) . '</a>. Porovnejte každou část se zdrojem. Co sedí, zaškrtněte. Co nesedí, napište do políčka „Vlastní text“. Texty se nikam neukládají, použijí se jen v bohoslužbě, kterou tlačítkem vytvoříte.</p>'
        . '<form method="post" action="?den=' . h($datum) . '">' . $casti
        . '<p><button>Vytvořit bohoslužbu s těmito texty (k tisku)</button> <a href="?den=' . h($datum) . '">Bohoslužbu bez úprav</a></p></form>'
        . "\n</body>\n</html>\n";
}

// ======================= SPUŠTĚNÍ =======================
/** Webový formulář s přepínači (při tisku se skryje). */
function formular(array $cfg, string $od): string
{
    $sel = fn(string $k, string $h) => $cfg[$k] === $h ? ' selected' : '';
    return '<form method="get" class="nastaveni"><input type="hidden" name="forma" value="1">'
        . '<p><b>Nastavení</b></p>'
        . '<div class="radek">od <input type="date" name="od" value="' . h($od) . '"></div>'
        . '<div class="radek">počet <input type="number" name="pocet" min="1" max="20" value="' . (int)$cfg['pocet'] . '"></div>'
        . '<div class="radek">Nanebevstoupení <select name="nanebevstoupeni"><option value="ctvrtek"' . $sel('nanebevstoupeni', 'ctvrtek') . '>ve čtvrtek</option><option value="nedele"' . $sel('nanebevstoupeni', 'nedele') . '>v neděli</option></select></div>'
        . '<div class="radek">Boží Tělo <select name="bozi_telo"><option value="ctvrtek"' . $sel('bozi_telo', 'ctvrtek') . '>ve čtvrtek</option><option value="nedele"' . $sel('bozi_telo', 'nedele') . '>v neděli</option></select></div>'
        . '<div class="radek"><label><input type="checkbox" name="vsedni" value="1"' . ($cfg['slavnosti_ve_vsedni_den'] ? ' checked' : '') . '> slavnosti i ve všední dny</label></div>'
        . '<div class="radek"><button>Vytvořit</button></div></form>';
}

function bsHlavni(array $CFG): void
{
    $cfg = $CFG;
    date_default_timezone_set($cfg['casove_pasmo']);
    $arg = [];
    $web = PHP_SAPI !== 'cli';
    if ($web) { $arg = $_GET; }
    else { foreach (array_slice($GLOBALS['argv'], 1) as $a) { if (preg_match('~^--([a-z_]+)=(.*)$~', $a, $m)) { $arg[$m[1]] = $m[2]; } } }

    // Přepínače z adresy / formuláře mají přednost před nastavením v souboru
    if (isset($arg['pocet'])) { $cfg['pocet'] = max(1, min(20, (int)$arg['pocet'])); }
    foreach (['nanebevstoupeni', 'bozi_telo'] as $k) {
        if (isset($arg[$k]) && in_array($arg[$k], ['ctvrtek', 'nedele'], true)) { $cfg[$k] = $arg[$k]; }
    }
    if ($web && isset($_GET['forma'])) { $cfg['slavnosti_ve_vsedni_den'] = isset($_GET['vsedni']); }
    elseif (isset($arg['vsedni'])) { $cfg['slavnosti_ve_vsedni_den'] = $arg['vsedni'] !== '0'; }
    if (!$cfg['slavnosti_ve_vsedni_den']) { $cfg['svatky_ve_vsedni_den'] = []; }

    $platne = fn($s) => is_string($s) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $s) && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4));
    if ($web && isset($arg['uprava']) && $platne($arg['uprava'])) {
        $d = new DateTimeImmutable($arg['uprava']);
        header('Content-Type: text/html; charset=utf-8');
        echo upravaStranka($d, $cfg);
        return;
    }
    $upravy = null;
    try {
        $odDatum = isset($arg['od']) && $platne($arg['od'])
            ? new DateTimeImmutable($arg['od'])
            : new DateTimeImmutable($cfg['vcetne_dnes'] ? 'today' : 'tomorrow');
        if (isset($arg['den']) && $platne($arg['den'])) {
            $d = new DateTimeImmutable($arg['den']);
            $p = popisDne($d, $cfg + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]);
            if ($p === null) { throw new RuntimeException('Tento den není nedělí ani nastavenou slavností.'); }
            $p['datum'] = $d;
            $prilezitosti = [$p];
            if ($web && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $upravy = zFormulare($_POST); }
        } else {
            $prilezitosti = dalsiPrilezitosti($odDatum, (int)$cfg['pocet'], $cfg);
        }
        $html = stranka($prilezitosti, $cfg, $web, $upravy, $web ? formular($cfg, $odDatum->format('Y-m-d')) : null);
    } catch (Throwable $e) {
        if ($web) { http_response_code(500); }
        $html = '<!doctype html><meta charset="utf-8"><p>Chyba: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    if (!$web && !empty($arg['vystup'])) { file_put_contents($arg['vystup'], $html); fwrite(STDERR, "Uloženo do {$arg['vystup']}\n"); return; }
    if ($web) { header('Content-Type: text/html; charset=utf-8'); }
    echo $html;
}

if (!defined('BS_KNIHOVNA')) { bsHlavni($CFG); }
