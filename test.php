<?php
declare(strict_types=1);
// Spuštění: php test.php   (testuje kalendář a rozbor stránek na ukázkových datech v test/misal)
define('BS_KNIHOVNA', true);
require __DIR__ . '/bohosluzba.php';
$cfg = $CFG;
$cfg['svatky_ve_vsedni_den'] = ['12-25','01-01','01-06','03-19','03-25','06-24','06-29','07-05','08-15','09-28','11-01','11-02','12-08'];
$cfg['zdroj'] = __DIR__ . '/test/misal';
$ok = 0; $chyb = 0;
function jeRovno($pop, $skut, $oc) { global $ok, $chyb; if ($skut === $oc) { $ok++; } else { $chyb++; echo "CHYBA: $pop: čekáno ", var_export($oc, true), ", je ", var_export($skut, true), "\n"; } }
$d = fn(string $s) => new DateTimeImmutable($s);
$nazev = function (string $den) use ($cfg, $d) { $p = popisDne($d($den), $cfg + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]); return $p['nazev'] ?? null; };

// Velikonoce a advent (ověřené známé hodnoty)
jeRovno('Velikonoce 2026', velikonoce(2026)->format('Y-m-d'), '2026-04-05');
jeRovno('Velikonoce 2025', velikonoce(2025)->format('Y-m-d'), '2025-04-20');
jeRovno('Velikonoce 2024', velikonoce(2024)->format('Y-m-d'), '2024-03-31');
jeRovno('Advent 2026', adventZacatek(2026)->format('Y-m-d'), '2026-11-29');
jeRovno('Advent 2025', adventZacatek(2025)->format('Y-m-d'), '2025-11-30');
// Liturgický rok: adv. 2025 = A, adv. 2026 = B, adv. 2027 = C
jeRovno('rok říjen 2026', cyklus($d('2026-10-11')), 'A');
jeRovno('rok prosinec 2026', cyklus($d('2026-12-06')), 'B');
jeRovno('rok leden 2028', cyklus($d('2028-01-09')), 'C');
// Neděle
jeRovno('27. v mezidobí', $nazev('2026-10-04'), '27. neděle v mezidobí');
jeRovno('28. v mezidobí', $nazev('2026-10-11'), '28. neděle v mezidobí');
jeRovno('Všech svatých v neděli', $nazev('2026-11-01'), 'Všech svatých');
jeRovno('Kristus Král', $nazev('2026-11-22'), 'Ježíše Krista Krále');
jeRovno('1. advent', $nazev('2026-11-29'), '1. neděle adventní');
jeRovno('8. 12. 2024 neděle adventní', $nazev('2024-12-08'), '2. neděle adventní');
jeRovno('8. 12. 2024 přeneseno na pondělí', $nazev('2024-12-09'), 'Neposkvrněného početí Panny Marie (přeneseno)');
jeRovno('Svatá rodina 2026', $nazev('2026-12-27'), 'Svaté Rodiny: Ježíše, Marie a Josefa');
jeRovno('Křest Páně 2027', $nazev('2027-01-10'), 'Křtu Páně');
jeRovno('2. v mezidobí 2027', $nazev('2027-01-17'), '2. neděle v mezidobí');
jeRovno('1. postní 2027', $nazev('2027-02-14'), '1. neděle postní');
jeRovno('Květná 2027', $nazev('2027-03-21'), 'Květná neděle');
jeRovno('Velikonoce 2027', $nazev('2027-03-28'), 'Neděle Zmrtvýchvstání Páně');
jeRovno('Letnice 2027', $nazev('2027-05-16'), 'Seslání Ducha Svatého (Letnice)');
jeRovno('Trojice 2027', $nazev('2027-05-23'), 'Nejsvětější Trojice');
jeRovno('Václav ve všední den', $nazev('2027-09-28'), 'Sv. Václava, hlavního patrona českého národa');
jeRovno('Všední den bez slavnosti', $nazev('2026-10-07'), null);
// Čtyři příští příležitosti po 4. 10. 2026
$dal = array_map(fn($p) => $p['datum']->format('m-d'), dalsiPrilezitosti($d('2026-10-05'), 4, $cfg));
jeRovno('příštích 4', $dal, ['10-11', '10-18', '10-25', '11-01']);
// Gloria
jeRovno('Gloria advent', popisDne($d('2026-12-06'), $cfg)['gloria'], false);
jeRovno('Gloria mezidobí', popisDne($d('2026-10-11'), $cfg)['gloria'], true);
jeRovno('Gloria slavnost v adventu', popisDne($d('2026-12-08'), $cfg)['gloria'], true);

// Rozbor stránek (ukázková data)
$p = popisDne($d('2026-10-11'), $cfg); $p['datum'] = $d('2026-10-11');
$t = nactiTexty($p, $cfg);
jeRovno('vstupní', $t['vstupni'], 'Testovací vstupní modlitba 28. neděle. Skrze našeho Pána.');
jeRovno('1. čtení rok A', $t['cteni1']['text'], 'Testovací první čtení A, věta jedna. Věta dvě.');
jeRovno('citace 1. čtení', $t['cteni1']['citace'], 'Iz 1,1-2');
jeRovno('refrén', $t['zalm']['refren'], 'Testovací odpověď A.');
jeRovno('počet slok', count($t['zalm']['sloky']), 2);
jeRovno('verš bez aleluja', $t['verš']['bez_aleluja'], 'Testovací verš A.');
jeRovno('evangelium', $t['evangelium']['text'], 'Testovací evangelium A.');
jeRovno('po přijímání', $t['po_prijimani'], 'Testovací modlitba po přijímání. Skrze Krista.');
$p['rok'] = 'C'; jeRovno('rok C', nactiTexty($p, $cfg)['cteni1']['text'], 'Testovací první čtení C, věta jedna. Věta dvě.');
// Slavnost bez nadpisů „v roce“: bloky čtení A, B, C jdou za sebou
$tr = popisDne($d('2027-05-23'), $cfg + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]); $tr['datum'] = $d('2027-05-23');
foreach (['A', 'B', 'C'] as $rokCteni) {
    $tr['rok'] = $rokCteni;
    jeRovno("Trojice rok $rokCteni", nactiTexty($tr, $cfg)['cteni1']['text'], "Čtení $rokCteni, věta jedna.");
}
$s = popisDne($d('2026-11-01'), $cfg); $s['datum'] = $d('2026-11-01');
$ts = nactiTexty($s, $cfg);
jeRovno('svatí: citace z nadpisu', $ts['cteni1']['citace'], 'Zj 7,2-4.9-14');
jeRovno('svatí: 2. čtení', $ts['cteni2']['text'], 'Test 2. čtení.');
jeRovno('svatí: žalm refrén (kurzíva)', $ts['zalm']['refren'], 'Toto je pokolení těch, kdo hledají tvou tvář.');
// Rozbalovací tlačítko „Čtení z Písma v roce B“ je nadpis a odkaz „zpět“ se do textu nedostane
$snip = rozdelNaSekce('<html><body><h3>x</h3><p><button>Čtení z Písma v roce B</button></p><h4>1. čtení</h4><p>Text.</p><p><a href="i.html">zpět</a></p></body></html>');
jeRovno('tlačítko je nadpis', $snip[2]['nazev'], 'Čtení z Písma v roce B');
jeRovno('zpět mimo text', array_map(fn($r) => $r['t'], $snip[3]['radky']), ['Text.']);
// Vlastní texty (kontrola): přepíší automatický text v paměti, ostatní části zůstanou; nic se neukládá
$pu = popisDne($d('2026-10-11'), $cfg); $pu['datum'] = $d('2026-10-11');
$tu = uplatniUpravy(nactiTexty($pu, $cfg), ['verš' => 'Vlastní verš.', 'cteni1' => 'Vlastní čtení.']);
jeRovno('úprava: verš', $tu['verš']['bez_aleluja'], 'Vlastní verš.');
jeRovno('úprava: 1. čtení', $tu['cteni1']['text'], 'Vlastní čtení.');
jeRovno('úprava: vstupní beze změny', $tu['vstupni'], 'Testovací vstupní modlitba 28. neděle. Skrze našeho Pána.');
// Formulář: prázdná pole se neberou, „sedí“ se eviduje
$zf = zFormulare(['ov' => ['verš' => " Text \r\n", 'cteni1' => ''], 'ok' => ['verš' => '1']]);
jeRovno('formulář: texty', $zf['texty'], ['verš' => 'Text']);
jeRovno('formulář: sedí', $zf['overeno'], ['verš' => true]);
jeRovno('přímluvy nejsou v kontrole', array_key_exists('primluvy', castyUpravy()), false);
// Žalm: v bohoslužbě jen číslo, PDF nápěvu z Korejse podle lekcionáře (verše, příležitost, cyklus)
jeRovno('žalm: číslo', zalmCislo('Žl 80 (79), 9+12.13-14.15-16.19-20'), 'Žl 80 (79)');
jeRovno('žalm: číslo bez závorky', zalmCislo('Žl 98,1.2-3ab.3cd-4'), 'Žl 98');
jeRovno('žalm: není žalm', zalmCislo('Iz 25,6-10a'), '');
$kc = $cfg; $kc['korejs'] = __DIR__ . '/test/korejs/';
jeRovno('korejs: 27. neděle A', korejsOdkaz('Žl 80 (79), 9+12.13-14.15-16.19-20', 'A', '27. neděle v mezidobí', $kc), __DIR__ . '/test/korejs/zalmy/TestPs080_05.pdf');
jeRovno('korejs: 1. adventní B', korejsOdkaz('Žl 80 (79), 2ac+3b.15-16.18-19', 'B', '1. neděle adventní', $kc), __DIR__ . '/test/korejs/zalmy/TestPs080_03.pdf');
jeRovno('korejs: 4. adventní C', korejsOdkaz('Žl 80 (79), 2ac+3b.15-16.18-19', 'C', '4. neděle adventní', $kc), __DIR__ . '/test/korejs/zalmy/TestPs080_03c.pdf');
jeRovno('korejs: nic k žalmu', korejsOdkaz('Iz 25,6-10a', 'A', '27. neděle v mezidobí', $kc), null);
// Více bohoslužeb najednou (web i CLI) se vykreslí bez varování a chyb
set_error_handler(function (int $no, string $str) { throw new ErrorException($str, 0, $no); });
$vice = [];
foreach (['2026-10-04', '2026-10-11', '2026-10-18', '2026-10-25'] as $dd) {
    $pp = popisDne($d($dd), $kc + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]);
    if ($pp !== null) { $pp['datum'] = $d($dd); $vice[] = $pp; }
}
$html = stranka($vice, $kc, true);
$html2 = stranka($vice, $kc, false, null, null);
restore_error_handler();
jeRovno('více bohoslužeb: počet krabiček', substr_count($html, 'class="krabice nezobrazovat"'), count($vice));
jeRovno('více bohoslužeb: legenda', substr_count($html2, 'Zkratky:'), 1);
echo "Hotovo: $ok v pořádku, $chyb chyb.\n";
exit($chyb ? 1 : 0);
