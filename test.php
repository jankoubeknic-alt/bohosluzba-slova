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
jeRovno('Povinná památka má přednost (7. 10.)', $nazev('2026-10-07'), 'Panny Marie Růžencové');
jeRovno('Feriální den 7. 10. je středa po 27. neděli', feriePodleObdobi($d('2026-10-07'))['nazev'], 'Středa po 27. neděli v mezidobí');
// Čtyři příští příležitosti od 5. 10. 2026 (včetně všedních dnů v mezidobí)
$dal = array_map(fn($p) => $p['datum']->format('m-d'), dalsiPrilezitosti($d('2026-10-05'), 4, $cfg));
jeRovno('příštích 4', $dal, ['10-05', '10-06', '10-07', '10-08']);
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
// Jedna bohoslužba (web i tisk) se vykreslí bez varování a chyb
set_error_handler(function (int $no, string $str) { throw new ErrorException($str, 0, $no); });
$pp = popisDne($d('2026-10-18'), $kc + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]);
$pp['datum'] = $d('2026-10-18');
$html = stranka($pp, $kc, true, null, formular($kc, '2026-10-18'));
$html2 = stranka($pp, $kc, false);
restore_error_handler();
jeRovno('bohoslužba: jedna krabička', substr_count($html, 'class="krabice nezobrazovat"'), 1);
jeRovno('bohoslužba: legenda', substr_count($html2, 'Zkratky:'), 1);
jeRovno('bohoslužba: titulek s datem', str_contains($html, '<title>Bohoslužba slova neděle 18. 10. 2026</title>'), true);
jeRovno('bohoslužba: bez seznamu a sidebaru', str_contains($html, 'class="bocni"'), false);
jeRovno('bohoslužba: odkaz na kontrolu nese nastavení', str_contains($html, 'href="?uprava=2026-10-18&amp;forma=1&amp;nanebevstoupeni=ctvrtek&amp;bozi_telo=ctvrtek&amp;vsedni=1"'), true);
jeRovno('bohoslužba: formulář má den, ne počet', [str_contains($html, 'name="den"'), str_contains($html, 'name="pocet"')], [true, false]);
$cfgDny = $cfg + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']];
$cfgSvatky = $cfgDny; $cfgSvatky['svatky_ve_vsedni_den'] = $CFG['svatky_ve_vsedni_den']; // výchozí seznam svátků
// Všední dny v mezidobí: stránka týdne NN_0D, feriální cyklus I/II podle roku, bez vyznání a Gloria
jeRovno('ferie: pondělí po 29. neděli', $nazev('2026-10-19'), 'Pondělí po 29. neděli v mezidobí');
jeRovno('ferie: soubor týdne', popisDne($d('2026-10-19'), $cfg)['odkaz']['soubor'], '29_01.htm');
jeRovno('ferie: cyklus II v sudém roce', popisDne($d('2026-10-19'), $cfg)['rok'], 'II');
jeRovno('ferie: cyklus I v lichém roce', popisDne($d('2027-10-18'), $cfgDny)['rok'], 'I');
jeRovno('ferie: soubor 2027', popisDne($d('2027-10-18'), $cfgDny)['odkaz']['soubor'], '29_01.htm');
jeRovno('ferie: pondělí 1. týdne po Křtu', $nazev('2026-01-12'), 'Pondělí 1. týdne v mezidobí');
jeRovno('ferie: úterý před popeleční středou', popisDne($d('2026-02-17'), $cfgDny)['odkaz']['soubor'], '06_02.htm');
jeRovno('ferie: popeleční středa (půst)', popisDne($d('2026-02-18'), $cfgDny)['odkaz']['soubor'], '00_03.htm');
jeRovno('ferie: pondělí po Trojici (týden 9)', feriePodleObdobi($d('2026-06-01'))['odkaz']['soubor'], '09_01.htm');
jeRovno('ferie: pondělí po Kristu Králi (týden 34)', popisDne($d('2026-11-23'), $cfg)['odkaz']['soubor'], '34_01.htm');
jeRovno('ferie: pondělí po 1. neděli adventní', popisDne($d('2026-11-30'), $cfgDny)['odkaz']['soubor'], '01_01.htm');
jeRovno('ferie: 17. prosince podle data', popisDne($d('2026-12-17'), $cfgDny)['odkaz']['soubor'], '12_17.htm');
jeRovno('ferie: 29. prosince (vánoce)', popisDne($d('2026-12-29'), $cfgDny)['nazev'], '29. prosince');
jeRovno('ferie: 26. prosince svatý zatím ne', popisDne($d('2026-12-26'), $cfgDny), null);
jeRovno('ferie: 7. ledna (vánoce)', popisDne($d('2027-01-07'), $cfgDny)['odkaz']['soubor'], '01_07.htm');
jeRovno('triduum: Zelený čtvrtek', popisDne($d('2026-04-02'), $cfgDny)['odkaz']['soubor'], '01ctvrtek.htm');
jeRovno('triduum: Zelený čtvrtek bez Vyznání, s Glorií', [popisDne($d('2026-04-02'), $cfgDny)['kredo'], popisDne($d('2026-04-02'), $cfgDny)['gloria']], [false, true]);
jeRovno('triduum: Velký pátek bez Glorie a Vyznání', [popisDne($d('2026-04-03'), $cfgDny)['odkaz']['soubor'], popisDne($d('2026-04-03'), $cfgDny)['kredo'], popisDne($d('2026-04-03'), $cfgDny)['gloria']], ['02patek.htm', false, false]);
jeRovno('vigilie: Velká sobota', popisDne($d('2026-04-04'), $cfgDny)['odkaz']['soubor'], '04vigilie_2.htm');
jeRovno('vigilie: příznak', popisDne($d('2026-04-04'), $cfgDny)['vigilie'] ?? false, true);
jeRovno('památka s vlastní stránkou (3. 12.)', [popisDne($d('2026-12-03'), $cfgDny)['nazev'], popisDne($d('2026-12-03'), $cfgDny)['odkaz']['soubor'], popisDne($d('2026-12-03'), $cfgDny)['hodnost']], ['sv. Františka Xaverského', '12_03.htm', 'pamatka']);
jeRovno('nepovinná památka se bez nastavení neslaví', popisDne($d('2026-12-04'), $cfgDny)['hodnost'], 'ferie');
jeRovno('Štěpán ve všední den (26. 12. 2026 je sobota)', [popisDne($d('2026-12-26'), $cfgSvatky)['nazev'], popisDne($d('2026-12-26'), $cfgSvatky)['odkaz']['soubor']], ['sv. Štěpána', '12_26.htm']);
jeRovno('Svatá rodina v neděli 27. 12. 2026 přebíjí svátek', popisDne($d('2026-12-27'), $cfgDny)['nazev'], 'Svaté Rodiny: Ježíše, Marie a Josefa');
jeRovno('ferie: pondělí Svatého týdne', popisDne($d('2026-03-30'), $cfgDny)['odkaz']['soubor'], '06_01.htm');
jeRovno('ferie: pondělí v oktávu velikonočním', popisDne($d('2026-04-06'), $cfgDny)['odkaz']['soubor'], '01_01.htm');
jeRovno('ferie: pondělí po 2. neděli velikonoční', popisDne($d('2026-04-13'), $cfgDny)['odkaz']['soubor'], '02_01.htm');
jeRovno('ferie: čtvrtek 6. týdne velikonočního (Nanebevstoupení) zatím ne', popisDne($d('2026-05-14'), $cfgDny)['nazev'], 'Nanebevstoupení Páně');
jeRovno('ferie: týden po Letnicích (týden 8)', feriePodleObdobi($d('2026-05-26'))['odkaz']['soubor'], '08_02.htm');
jeRovno('ferie: pondělí po Letnicích (týden 8)', popisDne($d('2026-05-25'), $cfgDny)['odkaz']['soubor'], '08_01.htm');
jeRovno('ferie: pondělí po Trojici (týden 9, 2026)', feriePodleObdobi($d('2026-06-01'))['odkaz']['soubor'], '09_01.htm');
jeRovno('ferie: 1. týden postní', popisDne($d('2026-02-23'), $cfgDny)['odkaz']['soubor'], '01_01.htm');
jeRovno('ferie: bez vyznání a Gloria', [popisDne($d('2026-10-19'), $cfg)['kredo'], popisDne($d('2026-10-19'), $cfg)['gloria']], [false, false]);
jeRovno('neděle v mezidobí: vyznání a Gloria', [popisDne($d('2026-10-18'), $cfg)['kredo'], popisDne($d('2026-10-18'), $cfg)['gloria']], [true, true]);
// Čtení ze stránky všedního dne: cyklus I/II se vybere podle roku, verš a evangelium jsou společné
$pf = popisDne($d('2026-10-19'), $cfg); $pf['datum'] = $d('2026-10-19');
$tf = nactiTexty($pf, $kc);
jeRovno('ferie II: 1. čtení z cyklu II', str_contains($tf['cteni1']['text'] ?? '', 'cyklu dva'), true);
jeRovno('ferie II: bez čtení z cyklu I', str_contains($tf['cteni1']['text'] ?? '', 'cyklu jedna'), false);
jeRovno('ferie: verš bez Aleluja', $tf['verš']['bez_aleluja'] ?? null, 'Testovací verš před evangeliem.');
jeRovno('ferie: evangelium', str_contains($tf['evangelium']['text'] ?? '', 'všedního dne'), true);
jeRovno('ferie: vstupní modlitba', str_contains($tf['vstupni'] ?? '', 'všední den'), true);
$pf1 = popisDne($d('2027-10-18'), $cfgDny); $pf1['datum'] = $d('2027-10-18');
$tf1 = nactiTexty($pf1, $kc);
jeRovno('ferie I: 1. čtení z cyklu I', str_contains($tf1['cteni1']['text'] ?? '', 'cyklu jedna'), true);
jeRovno('ferie I: bez chyb', $tf1['chyby'], []);
// Svátky ve všední den se nabízejí (výchozí seznam z NASTAVENÍ), ne jako všední dny
jeRovno('svátek: Proměnění ve všední den', popisDne($d('2026-08-06'), $cfgSvatky)['nazev'] ?? null, 'Proměnění Páně');
jeRovno('svátek: Povýšení kříže ve všední den', popisDne($d('2026-09-14'), $cfgSvatky)['nazev'] ?? null, 'Povýšení svatého Kříže');
jeRovno('svátek: Posvěcení lateránské baziliky', popisDne($d('2026-11-09'), $cfgSvatky)['hodnost'] ?? null, 'svatek');
jeRovno('svátek: Uvedení Páně do chrámu', popisDne($d('2026-02-02'), $cfgSvatky)['odkaz']['slozka'] ?? null, '08sanctoral');
// Pašije: evangelium, nadpis bez citace ne
jeRovno('pašije: cyklus A', typSekce('Pašije v ročním cyklu A – Mt 26,14-27,66'), 'evangelium');
jeRovno('pašije: Velký pátek', typSekce('Pašije – Jan 18,1-19,42'), 'evangelium');
jeRovno('pašije: nadpis bez citace', typSekce('Pašije'), null);
jeRovno('pašije: tlačítko roku', typSekce('Pašije v roce A'), null);
// Velikonoční vigilie (fixtura): sedm čtení a epištola s žalmy, evangelium podle roku
$pv = popisDne($d('2026-04-04'), $cfgDny); $pv['datum'] = $d('2026-04-04');
$tv = nactiTexty($pv, $kc);
jeRovno('vigilie: osm čtení (1.–7. a epištola)', count($tv['cteni_vigilie']), 8);
jeRovno('vigilie: 1. čtení', $tv['cteni_vigilie'][0]['citace'], 'Test 1,1-2');
jeRovno('vigilie: epištola je poslední', $tv['cteni_vigilie'][7]['citace'], 'Test 8,3-11');
jeRovno('vigilie: žalm u 1. čtení', $tv['cteni_vigilie'][0]['zalm']['citace'], 'Žl 104,1-2a.5-6');
jeRovno('vigilie: modlitba u 2. čtení', $tv['cteni_vigilie'][1]['modlitba'], 'Testovací modlitba po druhém čtení.');
jeRovno('vigilie: evangelium roku A', $tv['evangelium']['citace'], 'Test 9,1-10');
$pvB = $pv; $pvB['rok'] = 'B'; jeRovno('vigilie: evangelium roku B', nactiTexty($pvB, $kc)['evangelium']['citace'], 'Test 9,11-20');
jeRovno('vigilie: bez chyb s úplnými čteními', $tv['chyby'], []);
jeRovno('vigilie: bohoslužba slova má všech 8 čtení', count(array_filter(radkyVigilie($tv), fn($r) => $r[1] === 'Bohu díky.')), 8);
jeRovno('vigilie: bez Vyznání víry', str_contains(stranka($pv, $kc), 'Věřím v jednoho Boha'), false);
// Adventní všední den: hlavní čtení je první, volitelné „v cyklu A“ se nebere, „Čte-li se …“ se neukáže
$pa = popisDne($d('2026-11-30'), $cfgDny); $pa['datum'] = $d('2026-11-30');
$ta = nactiTexty($pa, $kc);
jeRovno('advent: hlavní 1. čtení', $ta['cteni1']['citace'], 'Test 2,1-5');
jeRovno('advent: bez volitelného čtení', str_contains($ta['cteni1']['text'] ?? '', 'alternativní'), false);
jeRovno('advent: bez věty „Čte-li se“', str_contains($ta['cteni1']['text'] ?? '', 'Čte-li'), false);
jeRovno('advent: hlavní text 1. čtení', str_contains($ta['cteni1']['text'] ?? '', 'Testovací hlavní čtení'), true);
jeRovno('advent: verš bez Aleluja', $ta['verš']['bez_aleluja'] ?? null, 'Testovací verš adventní.');
jeRovno('advent: žalm a evangelium', [$ta['zalm']['citace'] ?? null, $ta['evangelium']['citace'] ?? null], ['Žl 122,1-2', 'Test 8,5-11']);
// Stránka všedního dne: bez vyznání a Gloria, s označením feriálního cyklu
set_error_handler(function (int $no, string $str) { throw new ErrorException($str, 0, $no); });
$hf = stranka($pf, $kc);
restore_error_handler();
jeRovno('ferie: stránka bez vyznání víry', str_contains($hf, 'Věřím v jednoho Boha'), false);
jeRovno('ferie: stránka bez Gloria', str_contains($hf, 'Sláva na výsostech'), false);
jeRovno('ferie: stránka s feriálním cyklem', str_contains($hf, 'feriální cyklus II'), true);
echo "Hotovo: $ok v pořádku, $chyb chyb.\n";
exit($chyb ? 1 : 0);
