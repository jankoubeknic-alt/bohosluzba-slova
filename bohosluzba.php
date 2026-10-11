<?php
declare(strict_types=1);
/**
 * Generátor Bohoslužby slova: jediný soubor (generátor, web, CLI i vestavěné testy).
 * Texty © ČBK / liturgie.cz se stahují do cache/ a do repozitáře nepatří. Podrobnosti: README.md.
 *
 *   Web:    bohosluzba.php                        (výběr dne v kalendáři + výstup)
 *           bohosluzba.php?den=2026-10-18
 *   CLI:    php bohosluzba.php --den=2026-10-18 --vystup=bohosluzba.html [--zformular=2]
 *   Testy:  php bohosluzba.php --test
 *
 * Přepínače lze změnit v bloku NASTAVENÍ níže, nebo ve webovém formuláři / v adrese (adresa má přednost).
 */

// ======================= NASTAVENÍ =======================
$CFG = [
    // Odkud se berou texty: URL (končící /), nebo cesta k lokální kopii adresáře "misal".
    'zdroj' => getenv('BS_ZDROJ') ?: 'https://m.liturgie.cz/misal/',
    'cache' => __DIR__ . '/cache',          // kam se ukládají stažené stránky (musí být zapisovatelná)
    'cache_dni' => 90,
    'user_agent' => 'BohosluzbaSlova-generator/1.0 (osobni priprava bohosluzeb)',
    'korejs' => 'https://sdh.cz/korejs/',    // tabulka žalmů s nápěvy (Korejs); generátor jen odkazuje na PDF, nic nekopíruje

    // ---- PŘEPÍNAČE ----
    'nanebevstoupeni' => 'ctvrtek',          // 'ctvrtek' (všední den) nebo 'nedele' (přeneseno na neděli)
    'bozi_telo' => 'ctvrtek',                // 'ctvrtek' nebo 'nedele'
    'slavnosti_ve_vsedni_den' => true,       // true = nabízet i slavnosti ve všední dny; false = jen neděle
    // Které slavnosti a svátky ve všední den (měsíc-den) se nabízejí. Co nechcete, smažte.
    'svatky_ve_vsedni_den' => ['01-01', '01-06', '01-25', '02-02', '02-22', '03-19', '03-25', '04-23', '04-25', '04-29',
                               '05-03', '05-14', '05-16', '05-31', '06-24', '06-29', '07-03', '07-05', '07-11', '07-22',
                               '07-23', '07-25', '08-06', '08-09', '08-10', '08-15', '08-24', '09-08', '09-14', '09-21',
                               '09-28', '09-29', '10-18', '10-28', '11-01', '11-02', '11-09', '11-30', '12-08', '12-25',
                               '12-26', '12-27', '12-28'],
    // Památky svatých ve všední den: 'povinne' = jen povinné (mají texty na liturgie.cz), 'vsechny' i nepovinné (jen pokud mají stránku)
    'vzpominky' => 'povinne',
    'zesnuli_formular' => 1,                 // vzpomínka na zemřelé (2. 11.) má 3 mešní formuláře: 1, 2 nebo 3 (vstupní a po přijímání)
    'vcetne_dnes' => false,                  // výchozí den bez data: false = následující neděle (po dnešku), true = dnešní neděle, je-li dnes neděle
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

/** Sanktorální kalendář z liturgie.cz: 'MM-DD' => [hodnost, název, stránka v 08sanctoral (nebo null)].
 *  Hodnost: S slavnost, SV svátek, P povinná památka, NP nepovinná památka. Pevné slavnosti jsou v pevneSlavnosti(). */
const SANKTORAL = [
    '12-01' => ['NP', 'sv. Edmunda Kampiána', null],
    '12-03' => ['P', 'sv. Františka Xaverského', '12_03.htm'],
    '12-04' => ['NP', 'sv. Jana Damašského', null],
    '12-06' => ['NP', 'sv. Mikuláše', null],
    '12-07' => ['P', 'sv. Ambrože', '12_07.htm'],
    '12-08' => ['S', 'Panny Marie, počaté bez poskvrny prvotního hříchu', null],
    '12-09' => ['NP', 'sv. Jana Didaka Cuauhtlatoatzina', null],
    '12-10' => ['NP', 'Panny Marie Loretánské', null],
    '12-11' => ['NP', 'sv. Damasa I.', null],
    '12-12' => ['NP', 'Panny Marie Guadalupské', null],
    '12-13' => ['P', 'sv. Lucie', '12_13.htm'],
    '12-14' => ['P', 'sv. Jana od Kříže', '12_14.htm'],
    '12-21' => ['NP', 'sv. Petra Kanisia', null],
    '12-23' => ['NP', 'sv. Jana Kentského', null],
    '12-26' => ['SV', 'sv. Štěpána', '12_26.htm'],
    '12-27' => ['SV', 'sv. Jana', '12_27.htm'],
    '12-28' => ['SV', 'sv. Mláďátek', '12_28.htm'],
    '12-29' => ['NP', 'sv. Tomáše Becketa', null],
    '12-31' => ['NP', 'sv. Silvestra I.', null],
    '01-01' => ['S', 'Matky Boží, Panny Marie', '01_01.htm'],
    '01-02' => ['P', 'sv. Basila Velikého a Řehoře Naziánského', '01_02.htm'],
    '01-03' => ['NP', 'Nejsvětějšího Jména Ježíš', null],
    '01-06' => ['S', 'Zjevení Páně', null],
    '01-07' => ['NP', 'sv. Rajmunda z Peñafortu', null],
    '01-13' => ['NP', 'sv. Hilaria', null],
    '01-17' => ['P', 'sv. Antonína', '01_17.htm'],
    '01-18' => ['P', 'Panny Marie, Matky jednoty křesťanů', '01_18.htm'],
    '01-20' => ['NP', 'sv. Fabiána', null],
    '01-21' => ['P', 'sv. Anežky', '01_21.htm'],
    '01-22' => ['NP', 'sv. Vincence', null],
    '01-24' => ['P', 'sv. Františka Saleského', '01_24.htm'],
    '01-25' => ['SV', 'Obrácení sv. Pavla', '01_25.htm'],
    '01-26' => ['P', 'sv. Timoteje a Tita', '01_26.htm'],
    '01-27' => ['NP', 'sv. Anděly Mericiové', null],
    '01-28' => ['P', 'sv. Tomáše Akvinského', '01_28.htm'],
    '01-31' => ['P', 'sv. Jana Boska', '01_31.htm'],
    '02-02' => ['SV', 'Uvedení Páně do chrámu', '02_23.htm'],
    '02-03' => ['NP', 'sv. Blažeje', null],
    '02-05' => ['P', 'sv. Agáty', '02_05.htm'],
    '02-06' => ['P', 'sv. Pavla Mikiho a druhů', '02_06.htm'],
    '02-08' => ['NP', 'sv. Jeronýma Emilianiho', null],
    '02-10' => ['P', 'sv. Scholastiky', '02_10.htm'],
    '02-11' => ['NP', 'Panny Marie Lurdské', null],
    '02-17' => ['NP', 'sv. Alexia a druhů', null],
    '02-21' => ['NP', 'sv. Petra Damianiho', null],
    '02-22' => ['SV', 'Stolce sv. Petra', '02_22.htm'],
    '02-23' => ['P', 'sv. Polykarpa', '02_23.htm'],
    '02-27' => ['NP', 'sv. Řehoře z Nareku', null],
    '03-04' => ['NP', 'sv. Kazimíra', null],
    '03-07' => ['P', 'sv. Perpetuy a Felicity', '03_07.htm'],
    '03-08' => ['NP', 'sv. Jana z Boha', null],
    '03-09' => ['NP', 'sv. Františky Římské', null],
    '03-10' => ['NP', 'sv. Jana Ogilvie', null],
    '03-17' => ['NP', 'sv. Patrika', null],
    '03-18' => ['NP', 'sv. Cyrila Jeruzalémského', null],
    '03-19' => ['S', 'sv. Josefa', '03_19.htm'],
    '03-23' => ['NP', 'sv. Turibia z Mongroveja', null],
    '03-25' => ['S', 'Zvěstování Páně', '03_25.htm'],
    '04-02' => ['NP', 'sv. Františka z Pauly', null],
    '04-04' => ['NP', 'sv. Izidora', null],
    '04-05' => ['NP', 'sv. Vincence Ferrerského', null],
    '04-07' => ['P', 'sv. Jana Křtitele de la Salle', '04_07.htm'],
    '04-11' => ['P', 'sv. Stanislava', '04_11.htm'],
    '04-13' => ['NP', 'sv. Martina I.', null],
    '04-21' => ['NP', 'sv. Anselma', null],
    '04-23' => ['SV', 'sv. Vojtěcha', '04_23.htm'],
    '04-24' => ['NP', 'sv. Jiřího', null],
    '04-25' => ['SV', 'sv. Marka', '04_25.htm'],
    '04-28' => ['NP', 'sv. Petra Chanela', null],
    '04-29' => ['SV', 'sv. Kateřiny Sienské', '04_29.htm'],
    '04-30' => ['NP', 'sv. Zikmunda', null],
    '05-01' => ['NP', 'sv. Josefa, Dělníka', null],
    '05-02' => ['P', 'sv. Atanáše', '05_02.htm'],
    '05-03' => ['SV', 'sv. Filipa a Jakuba', '05_03.htm'],
    '05-06' => ['NP', 'sv. Jana Sarkandra', '05_06.htm'],
    '05-08' => ['NP', 'Panny Marie, Prostřednice všech milostí', null],
    '05-10' => ['NP', 'sv. Jana z Avily', null],
    '05-12' => ['NP', 'sv. Nerea a Achillea', null],
    '05-13' => ['NP', 'Panny Marie Fatimské', null],
    '05-14' => ['SV', 'sv. Matěje', '05_14.htm'],
    '05-16' => ['SV', 'sv. Jana Nepomuckého', '05_16.htm'],
    '05-18' => ['NP', 'sv. Jana I.', null],
    '05-20' => ['NP', 'sv. Klementa Marie Hofbauera', '05_20.htm'],
    '05-21' => ['NP', 'sv. Kryštofa Magallanese a druhů', null],
    '05-22' => ['NP', 'sv. Rity z Cascie', null],
    '05-25' => ['NP', 'sv. Bedy Ctihodného', null],
    '05-26' => ['P', 'sv. Filipa Neriho', '05_26.htm'],
    '05-27' => ['NP', 'sv. Augustina z Canterbury', null],
    '05-29' => ['NP', 'sv. Pavla VI.', null],
    '05-30' => ['NP', 'sv. Zdislavy', '05_30.htm'],
    '05-31' => ['SV', 'Navštívení Panny Marie', '05_31.htm'],
    '06-01' => ['P', 'sv. Justina', '06_01.htm'],
    '06-02' => ['NP', 'sv. Marcelina a Petra', null],
    '06-03' => ['P', 'sv. Karla Lwangy a druhů', '06_03.htm'],
    '06-05' => ['P', 'sv. Bonifáce', '06_05.htm'],
    '06-06' => ['NP', 'sv. Norberta', null],
    '06-09' => ['NP', 'sv. Efréma', null],
    '06-11' => ['P', 'sv. Barnabáše', '06_11.htm'],
    '06-13' => ['P', 'sv. Antonína z Padovy', '06_13.htm'],
    '06-15' => ['NP', 'sv. Víta', null],
    '06-19' => ['NP', 'sv. Jana Nepomuckého Neumanna', null],
    '06-21' => ['P', 'sv. Aloise Gonzagy', '06_21.htm'],
    '06-22' => ['NP', 'sv. Paulína Nolánského', null],
    '06-24' => ['S', 'Narození sv. Jana Křtitele', '06_24.htm'],
    '06-27' => ['NP', 'sv. Cyrila Alexandrijského', null],
    '06-28' => ['P', 'sv. Ireneje', '06_28.htm'],
    '06-29' => ['S', 'sv. Petra a Pavla', '06_29.htm'],
    '06-30' => ['NP', 'Svatých prvomučedníků římských', null],
    '07-03' => ['SV', 'sv. Tomáše', '07_03.htm'],
    '07-04' => ['NP', 'sv. Prokopa', '07_04.htm'],
    '07-05' => ['S', 'sv. Cyrila a Metoděje', '07_05.htm'],
    '07-06' => ['NP', 'sv. Marie Gorettiové', null],
    '07-09' => ['NP', 'sv. Augustina Žao Ronga, kněze a druhů', null],
    '07-11' => ['SV', 'sv. Benedikta', '07_11.htm'],
    '07-13' => ['NP', 'sv. Jindřicha', null],
    '07-14' => ['NP', 'bl. Hroznaty', null],
    '07-15' => ['P', 'sv. Bonaventury', '07_15.htm'],
    '07-16' => ['NP', 'Panny Marie Karmelské', null],
    '07-17' => ['NP', 'bl. Česlava a sv. Hyacinta', null],
    '07-20' => ['NP', 'sv. Apolináře', null],
    '07-21' => ['NP', 'sv. Vavřince z Brindisi', null],
    '07-22' => ['SV', 'sv. Marie Magdalény', '07_22.htm'],
    '07-23' => ['SV', 'sv. Brigity', '07_23.htm'],
    '07-24' => ['NP', 'sv. Šarbela Machlúfa', null],
    '07-25' => ['SV', 'sv. Jakuba', '07_25.htm'],
    '07-26' => ['P', 'sv. Jáchyma a Anny', '07_26.htm'],
    '07-27' => ['NP', 'sv. Gorazda a druhů', '07_27.htm'],
    '07-29' => ['P', 'sv. Marty, Marie a Lazara', '07_29.htm'],
    '07-30' => ['NP', 'sv. Petra Chryzologa', null],
    '07-31' => ['P', 'sv. Ignáce z Loyoly', '07_31.htm'],
    '08-01' => ['P', 'sv. Alfonsa Marie z Liguori', '08_01.htm'],
    '08-02' => ['NP', 'sv. Eusebia z Vercelli', null],
    '08-04' => ['P', 'sv. Jana Marie Vianneye', '08_04.htm'],
    '08-05' => ['NP', 'Posvěcení římské baziliky Panny Marie', null],
    '08-06' => ['SV', 'Proměnění Páně', '08_06.htm'],
    '08-07' => ['NP', 'sv. Sixta II.', null],
    '08-08' => ['P', 'sv. Dominika', '08_08.htm'],
    '08-09' => ['SV', 'sv. Terezie Benedikty od Kříže', '08_09.htm'],
    '08-10' => ['SV', 'sv. Vavřince', '08_10.htm'],
    '08-11' => ['P', 'sv. Kláry', '08_11.htm'],
    '08-12' => ['NP', 'sv. Jany Františky de Chantal', null],
    '08-13' => ['NP', 'sv. Ponciána, papeže, a Hippolyta, kněze', null],
    '08-14' => ['P', 'sv. Maxmiliána Maria Kolbeho', '08_14.htm'],
    '08-15' => ['S', 'Nanebevzetí Panny Marie', '08_15.htm'],
    '08-16' => ['NP', 'sv. Štěpána Uherského', null],
    '08-19' => ['NP', 'sv. Jana Eudese', null],
    '08-20' => ['P', 'sv. Bernarda', '08_20.htm'],
    '08-21' => ['P', 'sv. Pia X.', '08_21.htm'],
    '08-22' => ['P', 'Panny Marie Královny', '08_22.htm'],
    '08-23' => ['NP', 'sv. Růženy z Limy', null],
    '08-24' => ['SV', 'sv. Bartoloměje', '08_24.htm'],
    '08-25' => ['NP', 'sv. Benedikta, Jana, Matouše, Izáka a Kristina', null],
    '08-27' => ['P', 'sv. Moniky', '08_27.htm'],
    '08-28' => ['P', 'sv. Augustina', '08_28.htm'],
    '08-29' => ['P', 'Umučení sv. Jana Křtitele', '08_29.htm'],
    '09-03' => ['P', 'sv. Řehoře Velikého', '09_03.htm'],
    '09-07' => ['NP', 'sv. Melichara Grodeckého', null],
    '09-08' => ['SV', 'Narození Panny Marie', '09_08.htm'],
    '09-09' => ['NP', 'sv. Petra Klavera', null],
    '09-10' => ['NP', 'bl. Karla Spinoly', null],
    '09-12' => ['NP', 'Jména Panny Marie', null],
    '09-13' => ['P', 'sv. Jana Zlatoústého', '09_13.htm'],
    '09-14' => ['SV', 'Povýšení sv. Kříže', '09_14.htm'],
    '09-15' => ['P', 'Panny Marie Bolestné', '09_15.htm'],
    '09-16' => ['P', 'sv. Ludmily', '09_16.htm'],
    '09-17' => ['NP', 'sv. Kornélia a Cypriána', null],
    '09-19' => ['NP', 'sv. Januária', null],
    '09-20' => ['P', 'sv. Ondřeje Kim Taegona, Pavla Chong Hasanga a druhů', '09_20.htm'],
    '09-21' => ['SV', 'sv. Matouše', '09_21.htm'],
    '09-23' => ['P', 'sv. Pia z Pietrelciny', '09_23.htm'],
    '09-26' => ['NP', 'sv. Kosmy a Damiána', null],
    '09-27' => ['P', 'sv. Vincence z Pauly', '09_27.htm'],
    '09-28' => ['S', 'sv. Václava', '09_28.htm'],
    '09-29' => ['SV', 'sv. Michaela, Gabriela a Rafaela', '09_29.htm'],
    '09-30' => ['P', 'sv. Jeronýma', '09_30.htm'],
    '10-01' => ['P', 'sv. Terezie od Dítěte Ježíše', '10_01.htm'],
    '10-02' => ['P', 'svatých andělů strážných', '10_02.htm'],
    '10-04' => ['P', 'sv. Františka z Assisi', '10_04.htm'],
    '10-05' => ['NP', 'sv. Faustiny Kowalské', null],
    '10-06' => ['NP', 'sv. Bruna', null],
    '10-07' => ['P', 'Panny Marie Růžencové', '10_07.htm'],
    '10-09' => ['NP', 'sv. Dionýsia a druhů', null],
    '10-11' => ['NP', 'sv. Jana XXIII.', null],
    '10-12' => ['NP', 'sv. Radima', null],
    '10-14' => ['NP', 'sv. Kalista I.', null],
    '10-15' => ['P', 'sv. Terezie od Ježíše', '10_15.htm'],
    '10-16' => ['NP', 'sv. Hedviky', null],
    '10-17' => ['P', 'sv. Ignáce Antiochijského', '10_17.htm'],
    '10-18' => ['SV', 'sv. Lukáše', '10_18.htm'],
    '10-19' => ['NP', 'sv. Jana de Brébeuf a Izáka Joguese a druhů', null],
    '10-21' => ['NP', 'bl. Karla Rakouského', null],
    '10-22' => ['NP', 'sv. Jana Pavla II.', null],
    '10-23' => ['NP', 'sv. Jana Kapistránského', null],
    '10-24' => ['NP', 'sv. Antonína Marie Klareta', null],
    '10-28' => ['SV', 'sv. Šimona a Judy', '10_28.htm'],
    '10-31' => ['NP', 'sv. Wolfganga', null],
    '11-01' => ['S', 'Všech svatých', '11_01.htm'],
    '11-03' => ['NP', 'sv. Martina de Porres', null],
    '11-04' => ['P', 'sv. Karla Boromejského', '11_04.htm'],
    '11-09' => ['SV', 'Posvěcení lateránské baziliky', '11_09.htm'],
    '11-10' => ['P', 'sv. Lva Velikého', '11_10.htm'],
    '11-11' => ['P', 'sv. Martina', '11_11.htm'],
    '11-12' => ['P', 'sv. Josafata', '11_12.htm'],
    '11-13' => ['P', 'sv. Anežky České', '11_13.htm'],
    '11-15' => ['NP', 'sv. Alberta Velikého', null],
    '11-16' => ['NP', 'sv. Markéty Skotské', null],
    '11-17' => ['P', 'sv. Alžběty Uherské', '11_17.htm'],
    '11-18' => ['NP', 'Posvěcení římských bazilik svatých apoštolů Petra a Pavla', null],
    '11-21' => ['P', 'Zasvěcení Panny Marie v Jeruzalémě', '11_21.htm'],
    '11-22' => ['P', 'sv. Cecílie', '11_22.htm'],
    '11-23' => ['NP', 'sv. Klementa I.', null],
    '11-24' => ['P', 'sv. Ondřeje Dung-Laca a druhů', '11_24.htm'],
    '11-25' => ['NP', 'sv. Kateřiny Alexandrijské', null],
    '11-30' => ['SV', 'sv. Ondřeje', '11_30.htm'],
];

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
    // Vánoce (od 25. 12.) dřív než advent: advent končí 24. 12., i když je v prosinci
    if ($d->format('m-d') >= '12-25' || $d <= krtuPane($y)) { return 'vanoce'; }
    if ($d >= adventZacatek($y)) { return 'advent'; }
    if ($diff >= -46 && $diff < 0) { return 'pust'; }
    if ($diff >= 0 && $diff <= 49) { return 'velikonoce'; }
    return 'mezidobi';
}

function den(string $nazev, array $odkaz, string $hodnost, string $obdobi, string $rok, array $extra = []): array
{
    return array_merge([
        'nazev' => $nazev, 'odkaz' => $odkaz, 'hodnost' => $hodnost, 'obdobi' => $obdobi, 'rok' => $rok,
        // Gloria: neděle mimo advent a půst, slavnosti, svátky (ne všední dny ani vzpomínky).
        'gloria' => in_array($hodnost, ['slavnost', 'svatek'], true) || ($hodnost === 'nedele' && !in_array($obdobi, ['advent', 'pust'], true)),
        // Vyznání víry: jen neděle, slavnosti a svátky (ne všední dny, ne vzpomínka na zemřelé).
        'kredo' => in_array($hodnost, ['nedele', 'slavnost', 'svatek'], true),
    ], $extra);
}

/**
 * Číslo týdne v mezidobí, které nese neděle $s (a jeho všední dny: stránka NN_0D).
 * Týden po Křtu Páně je 1, po něm 2, 3 … do půstu; po Letnicích se počítá od konce roku (34 = Ježíše Krista Krále).
 * Null, pokud $s není v mezidobí (advent, vánoce, půst, velikonoce).
 */
function cisloTydneMezidobi(DateTimeImmutable $s): ?int
{
    $y = (int)$s->format('Y');
    $diff = dnuOd(velikonoce($y), $s);
    $adv = adventZacatek($y);
    $krtu = krtuPane($y);
    if ($s == $krtu) { return 1; }
    if ($diff >= 49 && $s < $adv) { return 35 - intdiv(dnuOd($s, $adv), 7); } // od Letnic (týden 8) do adventu
    if ($s > $krtu && $diff < -46) { return 2 + intdiv(dnuOd($krtu, $s) - 1, 7); }
    return null;
}

/** Slavnosti a svátky ze sanktorálu (se stránkou), které se nabízejí ve všední den. */
function sanktoralniSvatky(): array
{
    $vysl = [];
    foreach (SANKTORAL as $md => [$hodnost, $nazev, $stranka]) {
        if ($stranka === null || !in_array($hodnost, ['S', 'SV'], true)) { continue; }
        $vysl[$md] = [$nazev, odkaz('08sanctoral', $stranka), $hodnost === 'S' ? 'slavnost' : 'svatek'];
    }
    return $vysl;
}

/**
 * Památka svatého ve všední den, nebo null. Povinné památky se slaví vždy (kromě adventu od 17. 12.,
 * půstu, oktávů); nepovinné jen podle nastavení 'vzpominky'. Bez vlastní stránky se použijí feriální
 * čtení a v bohoslužbě je upozornění, že vlastní modlitby doplní uživatel z misálu.
 */
function pamatkaDne(DateTimeImmutable $d, array $cfg): ?array
{
    $md = $d->format('m-d');
    $zaz = SANKTORAL[$md] ?? null;
    if ($zaz === null || !in_array($zaz[0], ['P', 'NP'], true)) { return null; }
    if ($zaz[0] === 'NP' && $cfg['vzpominky'] !== 'vsechny') { return null; }
    $obd = obdobiDne($d);
    $diff = dnuOd(velikonoce((int)$d->format('Y')), $d);
    $slavi = match ($obd) {
        'mezidobi' => true,
        'advent' => $md < '12-17',
        'vanoce' => $md >= '01-02' && $md <= '01-12' && !in_array($md, ['01-06'], true),
        'velikonoce' => $diff >= 7,
        default => false,
    };
    if (!$slavi) { return null; }
    $f = feriePodleObdobi($d);
    if ($f === null) { return null; }
    $odkaz = $zaz[2] !== null ? odkaz('08sanctoral', $zaz[2]) : $f['odkaz'];
    return den($zaz[1], $odkaz, 'pamatka', $obd, $f['cyklus'], ['pamatka_bez_stranky' => $zaz[2] === null]);
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
    // Neděli přebíjejí jen pevné slavnosti a svátky Páně; svátky svatých jen všední den ($sl)
    $pevne = pevneSlavnosti();
    $sl = $pevne + sanktoralniSvatky();

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
        if (isset($pevne[$md])) {
            [$nazev, $odk, $h] = $pevne[$md];
            return den($nazev, $odk, $h, obdobiDne($d), $rok);
        }
        // Vánoce
        if ($md >= '12-26' && $md <= '12-31') { return den('Svaté Rodiny: Ježíše, Marie a Josefa', odkaz('02vanoce', 'SvRodina.htm'), 'svatek', 'vanoce', $rok); }
        if ($md >= '01-02' && $md <= '01-05') { return den('2. neděle po Narození Páně', odkaz('02vanoce', '02_00.htm'), 'nedele', 'vanoce', $rok); }
        $krtu = krtuPane($y);
        if ($d == $krtu) { return den('Křtu Páně', odkaz('02vanoce', 'KrtuPane.htm'), 'svatek', 'vanoce', $rok); }
        // Mezidobí
        if ($d == $adv->modify('-7 days')) { return den('Ježíše Krista Krále', odkaz('06mezidobi', 'Krista_Krale.htm'), 'slavnost', 'mezidobi', $rok); }
        $n = cisloTydneMezidobi($d);
        if ($n !== null) {
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
    // Velikonoční triduum: Zelený čtvrtek (Gloria, bez Vyznání) a Velký pátek (bez Glorie a Vyznání)
    $diffTriduum = dnuOd(velikonoce($y), $d);
    if ($diffTriduum === -3) { return den('Zelený čtvrtek', odkaz('04triduum', '01ctvrtek.htm'), 'slavnost', 'pust', $rok, ['kredo' => false]); }
    if ($diffTriduum === -2) { return den('Velký pátek', odkaz('04triduum', '02patek.htm'), 'triduum', 'pust', $rok, ['pasije' => true]); }
    // Velká sobota: velikonoční vigilie (večer), sedm čtení a epištola
    if ($diffTriduum === -1) { return den('Velikonoční vigilie', odkaz('04triduum', '04vigilie_2.htm'), 'slavnost', 'velikonoce', $rok, ['vigilie' => true, 'kredo' => false]); }
    // Památka svatého (povinná, případně i nepovinná podle nastavení)
    $pam = pamatkaDne($d, $cfg);
    if ($pam !== null) { return $pam; }
    // Všední den bez slavnosti: text ze stránky podle období
    $f = feriePodleObdobi($d);
    if ($f !== null) { return den($f['nazev'], $f['odkaz'], 'ferie', obdobiDne($d), $f['cyklus']); }
    return null;
}

/**
 * Všední den bez slavnosti: název, stránka na liturgie.cz a cyklus čtení ('I'/'II' v mezidobí, jinak '').
 * Null tam, kde zatím nejsou stránky (triduum, Duchovní týden, svatí o Vánocích …).
 */
function feriePodleObdobi(DateTimeImmutable $d): ?array
{
    $y = (int)$d->format('Y');
    $w = (int)$d->format('w');
    $md = $d->format('m-d');
    $dny = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
    $den = $dny[$w];
    $diff = dnuOd(velikonoce($y), $d);
    $diffS = $diff - $w; // Neděle, ke které den patří (týdenní stránka je pojmenovaná podle ní)
    $sbirka = fn(string $nazev, string $slozka, string $soubor, string $cyklus = '') => ['nazev' => $nazev, 'odkaz' => odkaz($slozka, $soubor), 'cyklus' => $cyklus];

    switch (obdobiDne($d)) {
        case 'mezidobi':
            $n = cisloTydneMezidobi($d->modify("-$w days"));
            if ($n === null) { return null; }
            $nazev = $n === 1 ? "$den 1. týdne v mezidobí" : "$den po $n. neděli v mezidobí";
            return $sbirka($nazev, '06mezidobi', sprintf('%02d_%02d.htm', $n, $w), $y % 2 === 1 ? 'I' : 'II');

        case 'advent':
            // 17.–24. 12. mají stránky podle data, dřívější dny podle týdne
            if ($md >= '12-17' && $md <= '12-24') { return $sbirka((int)$d->format('j') . '. prosince', '01advent', sprintf('12_%02d.htm', (int)$d->format('j'))); }
            $n = intdiv(dnuOd(adventZacatek($y), $d->modify("-$w days")), 7) + 1;
            return $sbirka("$den po $n. neděli adventní", '01advent', sprintf('%02d_%02d.htm', $n, $w));

        case 'vanoce':
            // Oktáv Narození Páně: 26.–28. 12. jsou svatí (zatím ne), 29.–31. 12. a 2.–5. a 7.–12. 1. jsou všední dny
            if (($md >= '12-29' && $md <= '12-31') || ($md >= '01-02' && $md <= '01-05') || ($md >= '01-07' && $md <= '01-12')) {
                return $sbirka((int)$d->format('j') . ($d->format('m') === '12' ? '. prosince' : '. ledna'), '02vanoce', sprintf('%s_%02d.htm', $d->format('m'), (int)$d->format('j')));
            }
            return null;

        case 'pust':
            // Popeleční středa a týden po ní (stránky 00_03 až 00_06)
            if ($diff >= -46 && $diff <= -43) {
                $nazev = $w === 3 ? 'Popeleční středa' : "$den po Popeleční středě";
                return $sbirka($nazev, '03pust', sprintf('00_%02d.htm', $w));
            }
            // Svatý týden: jen pondělí až středa; čtvrtek až sobota patří k triduu (zatím ne)
            if ($diff >= -6 && $diff <= -1) {
                return $w <= 3 ? $sbirka("$den Svatého týdne", '03pust', sprintf('06_%02d.htm', $w)) : null;
            }
            $n = intdiv($diffS + 49, 7); // 1. neděle postní = diffS −42
            return $n >= 1 && $n <= 5 ? $sbirka("$den po $n. neděli postní", '03pust', sprintf('%02d_%02d.htm', $n, $w)) : null;

        case 'velikonoce':
            // Oktáv velikonoční: stránky 01_01 až 01_06
            if ($diff >= 1 && $diff <= 6) { return $sbirka("$den v oktávu velikonočním", '05velikonoce', sprintf('01_%02d.htm', $w)); }
            // Čtvrtek 6. týdne (39) je vždy Nanebevstoupení, jeho stránka 06_04 není všední den; Seslání týden (50–55) zatím ne
            if ($diff === 39) { return null; }
            $n = intdiv($diffS, 7) + 1;
            return $n >= 2 && $n <= 7 ? $sbirka("$den po $n. neděli velikonoční", '05velikonoce', sprintf('%02d_%02d.htm', $n, $w)) : null;
    }
    return null;
}

/** Výchozí den bohoslužby: následující neděle po $dnes (nebo dnešní, je-li $vcetneDnes a dnes je neděle). */
function nasledujiciNedele(DateTimeImmutable $dnes, bool $vcetneDnes): DateTimeImmutable
{
    return $dnes->modify($vcetneDnes ? 'this sunday' : 'next sunday');
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
    return stahniUrl(rtrim($zdroj, '/') . '/' . $relativni, $relativni, $cfg);
}

/** Stáhne vzdálenou stránku (nebo ji vezme z cache/, nejdéle cache_dni dní). */
function stahniUrl(string $url, string $nazevSouboru, array $cfg): string
{
    // Cache
    @mkdir($cfg['cache'], 0775, true);
    $soubor = $cfg['cache'] . '/' . preg_replace('~[^A-Za-z0-9_.-]+~', '_', $nazevSouboru);
    if (is_file($soubor) && filemtime($soubor) > time() - $cfg['cache_dni'] * 86400) {
        return file_get_contents($soubor);
    }
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

/** Řádky tabulky žalmů podle lekcionáře (Korejs): odkaz na PDF, číslo, verše, příležitost, cyklus. */
function nactiKorejs(array $cfg): array
{
    static $cache = [];
    $zaklad = rtrim($cfg['korejs'], '/') . '/';
    if (isset($cache[$zaklad])) { return $cache[$zaklad]; }
    $cache[$zaklad] = [];
    try {
        $html = preg_match('~^https?://~', $zaklad)
            ? stahniUrl($zaklad . 'podlelekcionare.html', 'korejs_podlelekcionare.html', $cfg)
            : (@file_get_contents($zaklad . 'podlelekcionare.html') ?: '');
    } catch (Throwable $e) { return $cache[$zaklad]; }
    if ($html === '') { return $cache[$zaklad]; }
    $dom = nactiDom($html);
    foreach ($dom->getElementsByTagName('tr') as $tr) {
        $td = $tr->getElementsByTagName('td');
        if ($td->length !== 12 || !($a = $td->item(0)->getElementsByTagName('a')->item(0))) { continue; }
        $bunka = fn(int $i) => trim(preg_replace('~\s+~u', ' ', $td->item($i)->textContent));
        $cache[$zaklad][] = ['url' => $zaklad . $a->getAttribute('href'), 'bibl' => $bunka(2), 'verse' => $bunka(3), 'prilezitost' => $bunka(7), 'cyklus' => $bunka(8)];
    }
    return $cache[$zaklad];
}

/** Číslo žalmu z citace z misálu: „Žl 80 (79), 9+12…“ => „Žl 80 (79)“. */
function zalmCislo(string $citace): string
{
    return preg_match('~^(Žl?\s*\d+(?:\s*\(\d+\))?)~u', trim($citace), $m) ? $m[1] : '';
}

/** PDF žalmu na Korejsovi: shoda čísla žalmu, vezme se nejlepší shoda verší, příležitosti a cyklu. */
function korejsOdkaz(string $citace, string $rok, string $prilezitost, array $cfg): ?string
{
    if (!preg_match('~^Žl?\s*(\d+)\s*(?:\(\d+\))?\s*,?\s*(.*)$~u', trim($citace), $m)) { return null; }
    $verse = preg_replace('~\s+|Odp.*$~u', '', $m[2]);
    $kKorejs = ['I' => '1', 'II' => '2'][$rok] ?? $rok; // všední dny: feriální cyklus I/II je na Korejsovi 1/2
    $nejlepsi = null; $skore = -1;
    foreach (nactiKorejs($cfg) as $r) {
        if (!preg_match('~^Žl?\s*(\d+)~u', $r['bibl'], $mb) || $mb[1] !== $m[1]) { continue; }
        $s = 0;
        if ($verse !== '' && preg_replace('~\s+~u', '', $r['verse']) === $verse) { $s += 4; }
        if ($r['prilezitost'] === $prilezitost) { $s += 2; }
        if ($kKorejs !== '' && str_contains($r['cyklus'], $kKorejs)) { $s += 1; }
        if ($s > $skore) { $skore = $s; $nejlepsi = $r; }
    }
    return $nejlepsi['url'] ?? null;
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
    $radek = ''; $kurz = 0; $pozn = 0; $celkem = 0; $odst = 0;
    // misál sází červeně pokyny a odpovědi lidu, třídou „m“ pak editorské/metodické poznámky (odkazy na
    // dokumenty, vysvětlivky k obřadu) – obojí je jen doprovodný text, nikdy text k přečtení
    $flush = function () use (&$sekce, &$radek, &$kurz, &$pozn, &$celkem, &$odst) {
        $t = trim(preg_replace('~[ \t\x{00A0}]+~u', ' ', $radek));
        if ($t !== '') {
            $sekce[count($sekce) - 1]['radky'][] = ['t' => $t, 'kurziva' => $celkem > 0 && $kurz / $celkem > 0.6, 'poznamka' => $celkem > 0 && $pozn / $celkem > 0.6, 'odst' => $odst];
        }
        $radek = ''; $kurz = 0; $pozn = 0; $celkem = 0;
    };
    $walk = function (DOMNode $n, bool $kurziva, bool $poznamka) use (&$walk, &$sekce, &$radek, &$kurz, &$pozn, &$celkem, &$odst, $flush, $bloky) {
        foreach ($n->childNodes as $c) {
            if ($c instanceof DOMText) {
                $t = preg_replace('~\s+~u', ' ', $c->textContent);
                if (trim($t) !== '') {
                    $d = delka(trim($t)); $radek .= $t; $celkem += $d;
                    if ($kurziva) { $kurz += $d; }
                    if ($poznamka) { $pozn += $d; }
                } else { $radek .= ' '; }
            } elseif ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                if (in_array($tag, ['script', 'style', 'head'], true)) { continue; }
                // odkaz „zpět“ na konci stránky nepatří do textu
                if ($tag === 'a' && preg_match('~^zpět~iu', trim($c->textContent))) { continue; }
                $vlastniPoznamka = (bool)preg_match('~\b(red|m)\b~iu', $c->getAttribute('class'));
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
                    // nový blok (p/div/td/...) nedědí „červenost“ předka – jinak by chybějící „>“ v jednom
                    // řádku (chyba na webu) mohlo stáhnout poznámku na celý zbytek stránky
                    $flush(); $odst++;
                    $walk($c, $kurziva, $vlastniPoznamka);
                    $flush(); $odst++;
                } else {
                    $walk($c, $kurziva || in_array($tag, ['i', 'em'], true), $poznamka || $vlastniPoznamka);
                }
            }
        }
    };
    $walk($telo, false, false);
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
        // Pašije na Květnou neděli a Velký pátek: „Pašije v ročním cyklu A – Mt …“, „Pašije – Jan …“
        (bool)preg_match('~^Pašije\s+(v ročním cyklu [ABC]|[–—-])~iu', $nazev) => 'evangelium',
        default => null,
    };
}

/** Odstraní z citace poznámku z webu „(naznačené ze společných textů)“; citace zůstane jen s biblickým odkazem. */
function ocistiCitaci(string $citace): string
{
    return trim(preg_replace('~\s*\(naznačen[^)]*\)~u', '', $citace));
}

/**
 * Liturgie.cz u modliteb zkracuje obvyklé zakončení na tři tečky (např. „Skrze tvého Syna …“),
 * protože se předpokládá, že ho celebrant zná zpaměti. Doplní se celé znění (ověřeno i mimo liturgie.cz,
 * např. breviar.op.cz), aby ho čtenář nemusel dohledávat.
 */
function doplnZakonceniModlitby(string $t): string
{
    $t = rtrim($t);
    // „Skrze tvého Syna …“ bývá i uprostřed věty („Prosíme o to skrze tvého Syna …“) s malým s
    if (preg_match('~([Ss])krze tvého Syna\s*(?:\.\.\.+|…)\s*$~u', $t, $m)) {
        $pokracovani = ($m[1] === 'S' ? 'Skrze' : 'skrze') . ' tvého Syna Ježíše Krista, našeho Pána, neboť on s tebou v jednotě Ducha svatého žije a kraluje po všechny věky věků.';
        return preg_replace('~[Ss]krze tvého Syna\s*(?:\.\.\.+|…)\s*$~u', $pokracovani, $t);
    }
    $konce = [
        '~Neboť on s tebou v jednotě Ducha [Ss]vatého\s*(?:\.\.\.+|…)\s*$~u' => 'Neboť on s tebou v jednotě Ducha svatého žije a kraluje po všechny věky věků.',
        '~Neboť on s tebou v jednotě\s*(?:\.\.\.+|…)\s*$~u' => 'Neboť on s tebou v jednotě Ducha svatého žije a kraluje po všechny věky věků.',
        '~Neboť ty žiješ a kraluješ s Bohem Otcem v jednotě\s*(?:\.\.\.+|…)\s*$~u' => 'Neboť ty žiješ a kraluješ s Bohem Otcem v jednotě Ducha svatého po všechny věky věků.',
        '~Neboť ty žiješ a kraluješ s Bohem Otcem\s*(?:\.\.\.+|…)\s*$~u' => 'Neboť ty žiješ a kraluješ s Bohem Otcem v jednotě Ducha svatého po všechny věky věků.',
    ];
    foreach ($konce as $vzor => $plne) {
        if (preg_match($vzor, $t)) { return preg_replace($vzor, $plne, $t); }
    }
    return $t;
}

function citaceZNadpisu(string $nazev): string
{
    // „Mezizpěv – Žl 1“, ale i „1. čtení- Iz 26,1“ (pomlčka bez mezery za slovem čtení)
    return preg_match('~(?:\s[–—-]\s|čtení\s*[–—-]\s*)(.+)$~u', $nazev, $m) ? ocistiCitaci(trim($m[1])) : '';
}

function jePasazCitace(string $t): bool
{
    return delka($t) < 80 && preg_match('~\d~', $t) && !preg_match('~[.!?]$~u', rtrim($t)) && preg_match('~^[1-3]?\s?[\p{L}]{1,12}\.?\s*\d~u', $t);
}

/** Vybere sekce pro daný rok A/B/C. */
function vyberCteni(array $sekce, string $rok): array
{
    $spolecne = []; $podleRoku = []; $aktRok = null; $vEvangelium = false;
    foreach ($sekce as $s) {
        // „Evangelium podle ročního cyklu“: tlačítka „A: Mt 17,1–9“ jsou evangelia pro daný rok
        if (preg_match('~^Evangelium podle ročního cyklu~iu', $s['nazev'])) { $vEvangelium = true; continue; }
        if ($vEvangelium && preg_match('~^([ABC]):\s*(.+)$~u', $s['nazev'], $m)) {
            $podleRoku[$m[1]]['evangelium'] = $s + ['nazev' => 'Evangelium – ' . $m[2]];
            continue;
        }
        if (preg_match('~v roce ([ABC])\b~u', $s['nazev'], $m)) { $aktRok = $m[1]; continue; }
        // Všední dny: dva rozbalovací cykly (feriální I a II); verš a evangelium jsou společné
        if (preg_match('~^Roční cyklus ([12])$~u', $s['nazev'], $m)) { $aktRok = $m[1] === '1' ? 'I' : 'II'; continue; }
        // Volitelné čtení „1. čtení v cyklu A“ (u adventních dnů) není hlavní; hlavní je první v pořadí
        if (preg_match('~ v cyklu [ABC]$~u', $s['nazev'])) { continue; }
        $typ = typSekce($s['nazev']);
        if ($typ !== null) {
            // prázdný nadpis bez textu (např. „1. čtení a žalm“) nebere místo skutečnému čtení
            if (!$s['radky']) { continue; }
            if ($aktRok !== null && !(in_array($aktRok, ['I', 'II'], true) && in_array($typ, ['verš', 'evangelium'], true))) {
                $podleRoku[$aktRok][$typ] = $s;
            } elseif (!isset($spolecne[$typ])) { $spolecne[$typ] = $s; }
        } elseif ($s['uroven'] <= 4 && $s['uroven'] > 0 && $aktRok !== null && !preg_match('~^SEKVENCE$~iu', $s['nazev'])) {
            $aktRok = null; // konec bloku čtení (Vyznání víry, Modlitba nad dary ...); sekvence k bloku patří
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

/**
 * Řádek je pokyn, editorská poznámka nebo odpověď lidu, ne text k přečtení: celý je červený nebo má
 * třídu „m“ (liturgická typografie pokynů/poznámek a odpovědí), je to jen odpověď „O: Amen.“, nebo
 * uvádí alternativu („Nebo:“ – jen slovo „Nebo“ je červené, zbytek řádku ne).
 */
function jePokynRadku(array $r): bool
{
    return !empty($r['poznamka'])
        || (bool)preg_match('~^[OVP]\s*[:.]\s*Amen\.?\s*$~iu', $r['t'])
        || (bool)preg_match('~^Nebo\s*:~u', $r['t']);
}

/**
 * Spojí řádky sekce do jednoho textu modlitby. Pokyn před textem („Neříká se Modleme se“) se přeskočí;
 * na pokynu/odpovědi za textem („O: Amen“, „Nebo:“ s další variantou modlitby) se vezme jen to, co je
 * před ním – jedna stránka má často dvě modlitby na výběr a my chceme jen tu první.
 */
function textModlitby(array $radky): string
{
    $t = [];
    foreach ($radky as $r) {
        if (jePokynRadku($r)) { if ($t) { break; } continue; }
        $t[] = $r['t'];
    }
    // pojistka, kdyby se odpověď lidu objevila jako holé „Amen.“ bez O:/V: a bez červené
    return trim(preg_replace('~\s*Amen\.?\s*$~u', '', trim(implode(' ', $t))));
}

/** Text první sekce, jejíž nadpis sedí na regex a která má text (prázdné nadpisy se přeskočí). */
function sekceTextu(array $sekce, string $regex): ?string
{
    foreach ($sekce as $s) {
        if (preg_match($regex, $s['nazev'])) {
            $txt = textModlitby($s['radky']);
            if ($txt !== '') { return $txt; }
        }
    }
    return null;
}

function rozborCteni(array $s): array
{
    $radky = $s['radky'];
    // věta „Čte-li se toto čtení na …, může se dnes vzít:“ uvádí volitelné čtení; text za ní nepatří k tomuto čtení
    foreach ($radky as $i => $r) { if (preg_match('~^Čte-li se~u', $r['t'])) { $radky = array_slice($radky, 0, $i); break; } }
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
        // pokyn nebo nabídka jiného čtení (volitelná varianta ročního cyklu) za textem už k němu nepatří
        if (jePokynRadku($r)) { if ($odstavce) { break; } continue; }
        if (preg_match('~^(Slovo Boží|Slovo Páně|Evangelium Páně|Zde je slovo|Slyšeli jsme)~u', $r['t'])) { continue; }
        $odstavce[$r['odst']][] = $r['t'];
    }
    $text = implode("\n\n", array_map(fn($o) => implode(' ', $o), $odstavce));
    return ['citace' => ocistiCitaci($citace), 'uvod' => $uvod, 'text' => trim($text)];
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
    foreach ($radky as $r) {
        // pokyn nebo nabídka jiného zpěvu („Nebo: Aleluja.“) za textem už k žalmu nepatří
        if (jePokynRadku($r)) { if ($sloky) { break; } continue; }
        $sloky[$r['odst']][] = $r['t'];
    }
    return ['citace' => ocistiCitaci($citace), 'refren' => $refren, 'sloky' => array_values(array_map(fn($o) => implode("\n", $o), $sloky))];
}

function rozborVerse(array $s): array
{
    $citace = citaceZNadpisu($s['nazev']);
    $radky = $s['radky'];
    if ($radky && jePasazCitace($radky[0]['t'])) { $c = array_shift($radky)['t']; $citace = $citace ?: $c; }
    $text = textModlitby($radky);
    $bezAleluja = trim(preg_replace(['~^\s*Aleluja[.,!]?\s*~u', '~\s*Aleluja[.,!]?\s*$~u'], '', $text));
    return ['citace' => ocistiCitaci($citace), 'text' => $text, 'bez_aleluja' => $bezAleluja];
}

/** Sekce jednoho mešního formuláře (vzpomínka na zemřelé): od „N. mešní formulář“ do dalšího formuláře nebo „Preface“. */
function blokFormulare(array $sekce, int $n): array
{
    $blok = []; $v = false;
    foreach ($sekce as $s) {
        if (preg_match('~^(\d)\. mešní formulář~iu', $s['nazev'], $m)) { if ($v) { break; } $v = (int)$m[1] === $n; continue; }
        if ($v && preg_match('~^Preface~iu', $s['nazev'])) { break; }
        if ($v) { $blok[] = $s; }
    }
    return $blok;
}

/**
 * Čtení velikonoční vigilie: 1.–7. čtení s žalmy a modlitbami, pak epištola s žalmem.
 * Evangelium se nebere tady (vybírá se podle roku A/B/C jako jinde).
 */
function ctenyVigilie(array $sekce): array
{
    $vysl = []; $posledni = null;
    foreach ($sekce as $s) {
        $nazev = $s['nazev'];
        if ((preg_match('~^\d\.\s*čtení~iu', $nazev) || preg_match('~^Epištola~iu', $nazev)) && $s['radky']) {
            $vysl[] = rozborCteni($s) + ['zalm' => null, 'modlitba' => null];
            $posledni = count($vysl) - 1;
        } elseif (preg_match('~^Mezizpěv~iu', $nazev) && $posledni !== null && $vysl[$posledni]['zalm'] === null) {
            $vysl[$posledni]['zalm'] = rozborZalmu($s);
        } elseif ($nazev === 'Modlitba' && $posledni !== null && $vysl[$posledni]['modlitba'] === null && $s['radky']) {
            $vysl[$posledni]['modlitba'] = doplnZakonceniModlitby(textModlitby($s['radky']));
        } elseif (preg_match('~^Evangelium~iu', $nazev)) {
            break;
        }
    }
    return $vysl;
}

/**
 * Čtení nabízené jako „1. čtení – X nebo Y“: nadpis je prázdný a texty jsou v tlačítkách „X“, „Y“.
 * Vezme se první tlačítko (hlavní varianta), jeho text se přejmenuje na citaci.
 */
function prvniNabidkaCteni(array $sekce): ?array
{
    foreach ($sekce as $i => $s) {
        if (typSekce($s['nazev']) !== 'cteni1' || $s['radky']) { continue; }
        for ($j = $i + 1; $j < count($sekce) && $j <= $i + 4; $j++) {
            if ($sekce[$j]['radky'] && typSekce($sekce[$j]['nazev']) === null) {
                return ['nazev' => '1. čtení – ' . $sekce[$j]['nazev'], 'uroven' => $sekce[$j]['uroven'], 'radky' => $sekce[$j]['radky']];
            }
        }
    }
    return null;
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
    // Vstupní modlitba je u některých dnů vánočního období ve dvou zněních (před / po slavnosti Zjevení Páně)
    $datum = $prilezitost['datum'] ?? null;
    $po = $datum instanceof DateTimeImmutable && $datum->format('m') === '01' && $datum->format('d') >= '07';
    $vysl['vstupni'] = sekceTextu($sekce, '~^Vstupní modlitba~iu') ?? sekceTextu($sekce, $po ? '~^Po slavnosti Zjevení~iu' : '~^Před slavností Zjevení~iu')
        ?? sekceTextu($sekce, '~^Modlitba$~u'); // Velký pátek: úvodní modlitba se jmenuje jen „Modlitba“
    $vysl['po_prijimani'] = sekceTextu($sekce, '~^Modlitba po přijímání~iu');
    // Vigilie: osm čtení (1.–7. a epištola) s žalmy a modlitbami; evangelium jako jinde
    if (!empty($prilezitost['vigilie'])) {
        $vysl['cteni_vigilie'] = ctenyVigilie($sekce);
        $vyber = vyberCteni($sekce, $prilezitost['rok']);
        if ($vyber['evangelium']) { $vysl['evangelium'] = rozborCteni($vyber['evangelium']); }
        if (count($vysl['cteni_vigilie']) < 8) { $vysl['chyby'][] = 'chybí: některá čtení vigilie (doplňte z misálu)'; }
        if (empty($vysl['evangelium'])) { $vysl['chyby'][] = 'chybí: evangelium'; }
        return $vysl;
    }
    // Obřad zesnulých (2. 11.) má tři mešní formuláře. Vstupní modlitbu a modlitbu po přijímání bereme z vybraného;
    // čtení a evangelia k formulářům na stránce navázaná nejsou, ty se doplní z feriálního dne.
    $zesnuli = ($prilezitost['hodnost'] ?? '') === 'zesnuli';
    if ($zesnuli) {
        $vysl['formular'] = max(1, min(3, (int)($cfg['zesnuli_formular'] ?? 1)));
        $blok = blokFormulare($sekce, $vysl['formular']);
        $vysl['vstupni'] = sekceTextu($blok, '~^Vstupní modlitba~iu') ?? $vysl['vstupni'];
        $vysl['po_prijimani'] = sekceTextu($blok, '~^Modlitba po přijímání~iu') ?? $vysl['po_prijimani'];
    }
    $vyber = $zesnuli ? [] : vyberCteni($sekce, $prilezitost['rok']);
    if (!$zesnuli && empty($vyber['cteni1'])) { $vyber['cteni1'] = prvniNabidkaCteni($sekce); }
    if ($vyber['cteni1'] ?? null) { $vysl['cteni1'] = rozborCteni($vyber['cteni1']); }
    if ($vyber['cteni2'] ?? null) { $vysl['cteni2'] = rozborCteni($vyber['cteni2']); }
    if ($vyber['zalm'] ?? null) { $vysl['zalm'] = rozborZalmu($vyber['zalm']); }
    if ($vyber['verš'] ?? null) { $vysl['verš'] = rozborVerse($vyber['verš']); }
    if ($vyber['evangelium'] ?? null) { $vysl['evangelium'] = rozborCteni($vyber['evangelium']); }
    // na webu zkrácené zakončení modlitby („Skrze tvého Syna …“) se doplní celé, ať ho není třeba dohledávat
    if ($vysl['vstupni'] !== null) { $vysl['vstupni'] = doplnZakonceniModlitby($vysl['vstupni']); }
    if ($vysl['po_prijimani'] !== null) { $vysl['po_prijimani'] = doplnZakonceniModlitby($vysl['po_prijimani']); }
    $kontrola = ['vstupni' => 'vstupní modlitba', 'po_prijimani' => 'modlitba po přijímání', 'cteni1' => '1. čtení', 'zalm' => 'žalm', 'verš' => 'verš před evangeliem', 'evangelium' => 'evangelium'];
    $spolecneTexty = false;
    foreach ($sekce as $s) { if (str_contains($s['nazev'], 'společných')) { $spolecneTexty = true; break; } }
    foreach ($kontrola as $k => $popis) {
        if (empty($vysl[$k])) { $vysl['chyby'][] = "chybí: $popis" . ($spolecneTexty ? ' (stránka odkazuje na společné texty, doplňte z misálu)' : ''); }
    }
    return doplnZeDne($vysl, $prilezitost, $cfg);
}

/**
 * Památka nebo vzpomínka bez vlastního textu: chybějící části se doplní z feriálního dne téhož data.
 * Misál pro bohoslužbu slova doporučuje „čtení ze dne“, společné texty jsou jen možnost; doplněné části se uvedou jako informace.
 */
function doplnZeDne(array $vysl, array $prilezitost, array $cfg): array
{
    $chybi = array_filter($vysl['chyby'] ?? [], fn($c) => str_starts_with($c, 'chybí:'));
    if (!$chybi || !in_array($prilezitost['hodnost'] ?? '', ['pamatka', 'zesnuli', 'svatek'], true) || !($prilezitost['datum'] ?? null)) { return $vysl; }
    $den = feriePodleObdobi($prilezitost['datum']);
    if ($den === null) { return $vysl; }
    $fer = nactiTexty(['odkaz' => $den['odkaz'], 'rok' => $den['cyklus'], 'datum' => $prilezitost['datum'], 'hodnost' => 'ferie', 'nazev' => $den['nazev']], $cfg);
    $popisy = ['vstupni' => 'vstupní modlitba', 'po_prijimani' => 'modlitba po přijímání', 'cteni1' => '1. čtení', 'zalm' => 'žalm', 'verš' => 'verš před evangeliem', 'evangelium' => 'evangelium'];
    $doplneno = [];
    foreach ($popisy as $k => $popis) {
        if (!empty($vysl[$k]) || empty($fer[$k])) { continue; }
        $vysl[$k] = $fer[$k];
        $doplneno[] = $popis;
        $vysl['chyby'] = array_values(array_filter($vysl['chyby'], fn($c) => !str_starts_with($c, "chybí: $popis")));
    }
    $vysl['doplneno'] = $doplneno;
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

    $r = array_merge($r, radkyZalmu($t['zalm'] ?? null, $t['zalm']['citace'] ?? ''));

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

    $r = array_merge($r, radkyEvangelium($t));
    if ($p['kredo']) {
        $r[] = ['pokyn', 'Krédo. Stojí. Může se zpívat.'];
        $r[] = ['P', 'Povstaňme a vyznejme svou víru.'];
        $r[] = ['Vš', KREDO];
    }
    return $r;
}

/**
 * Žalm nebo kantikum. Žalm s číslem jde na Korejs (text a nápěv se nevypisují). Kantikum (např. Magnifikat)
 * Korejs nemá, proto se jeho refrén a sloky vypíšou přímo.
 */
function radkyZalmu(?array $zalm, string $citace): array
{
    $cislo = zalmCislo($citace);
    if ($cislo !== '') {
        return [['pokyn', 'Žalm. Zpívá Honza nebo varhanice Lenka. Lid sedí. (' . $cislo . ') – text a nápěv v Korejsovi.']];
    }
    $r = [['pokyn', 'Kantikum' . ($citace !== '' ? " ($citace)" : '') . '. Zpívá Honza nebo varhanice Lenka. Lid sedí.']];
    if (trim((string)($zalm['refren'] ?? '')) !== '') { $r[] = ['Vš', $zalm['refren']]; }
    foreach ($zalm['sloky'] ?? [] as $sloka) { $r[] = ['Ž', $sloka]; }
    if (count($r) === 1) { $r[] = ['pokyn', '(text doplňte z misálu)']; }
    return $r;
}

/** Evangelium a homilie (společné pro běžnou bohoslužbu slova i vigilii). */
function radkyEvangelium(array $t): array
{
    $e = $t['evangelium'] ?? null;
    return [
        ['pokyn', 'Evangelium. Stojí. Bez „Pán s vámi“, bez kadidla a bez kříže nad knihou.' . ($e && $e['citace'] ? ' (' . $e['citace'] . ')' : '')],
        ['P', $e && $e['uvod'] ? $e['uvod'] : 'Slova svatého evangelia podle ...'],
        ['Vš', 'Sláva tobě, Pane.'],
        ['P', vlastni($e['text'] ?? null, 'VLASTNÍ TEXT - EVANGELIUM.')],
        ['P', 'Slyšeli jsme slovo Boží.'],
        ['Vš', 'Chvála tobě, Kriste.'],
        ['pokyn', 'Homilie. Lid sedí. Z knížky Minutěnky, nebo z breviáře (modlitba se čtením daného dne).'],
        ['pokyn', 'P čte kázání z knížky. – chvíle ticha –'],
    ];
}

/** Velikonoční vigilie: sedm čtení s žalmy a modlitbami, epištola, pak Aleluja a evangelium. */
function radkyVigilie(array $t): array
{
    $r = [];
    $cisla = ['1.', '2.', '3.', '4.', '5.', '6.', '7.'];
    foreach ($t['cteni_vigilie'] ?? [] as $i => $c) {
        $vedle = $cisla[$i] ?? 'Epištola';
        $r[] = ['pokyn', ($i < 7 ? "$vedle čtení." : 'Epištola.') . ($c['citace'] ? ' (' . $c['citace'] . ')' : '')];
        $r[] = ['L', $c['uvod'] ?: 'Čtení z ...'];
        $r[] = ['L', vlastni($c['text'] ?? null, 'VLASTNÍ TEXT - ' . ($i < 7 ? "$vedle ČTENÍ" : 'EPIŠTOLA') . '.')];
        $r[] = ['L', 'Slyšeli jsme slovo Boží.'];
        $r[] = ['Vš', 'Bohu díky.'];
        if (!empty($c['zalm'])) { $r = array_merge($r, radkyZalmu($c['zalm'], $c['zalm']['citace'] ?? '')); }
        if (!empty($c['modlitba'])) { $r[] = ['P', vlastni($c['modlitba'], 'VLASTNÍ MODLITBA.')]; }
    }
    $r[] = ['pokyn', 'Zpěv Aleluja. Stojí všichni. Aleluja se zpívá třikrát, zpěvák začíná, ostatní opakují. Verš v misálu není, zpívá se jen Aleluja.'];
    return array_merge($r, radkyEvangelium($t));
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
/** Označení cyklu do výstupu: liturgický rok A/B/C u neděl a slavností, feriální cyklus I/II u všedních dnů. */
function oznaceniRoku(array $p): string
{
    if (in_array($p['rok'], ['I', 'II'], true)) { return 'feriální cyklus ' . $p['rok']; }
    return $p['rok'] === '' ? 'všední den' : 'liturgický rok ' . $p['rok'];
}

function bohosluzbaHtml(array $p, array $t): string
{
    $hlava = '<header id="text"><h2>' . h($p['nazev']) . '</h2>'
        . '<p class="datum">' . h(cesDatum($p['datum'])) . ' · ' . h(oznaceniRoku($p)) . '</p>';
    $hlava .= '<p class="zdroj">Zdroj textů: <a href="' . h((string)$t['zdroj_url']) . '">' . h((string)$t['zdroj_url']) . '</a> (Český misál a Mešní lekcionář, © ČBK; vybráno pro ' . h(oznaceniRoku($p)) . ').</p>';
    if ($t['chyby']) {
        $hlava .= '<p class="chyba"><b>Pozor:</b> ' . h(implode('; ', $t['chyby'])) . '. Doplňte ručně nebo zkontrolujte stránku na liturgie.cz.</p>';
    }
    if ($p['hodnost'] === 'zesnuli') {
        $hlava .= '<p class="chyba"><b>Pozor:</b> vzpomínka na zemřelé má tři mešní formuláře. Vstupní modlitba a modlitba po přijímání jsou z ' . (int)($t['formular'] ?? 1) . '. formuláře (volba v nastavení). Čtení a evangelium jsou z feriálního dne; vlastní texty zadejte v kontrole.</p>';
    }
    if (!empty($t['doplneno'])) {
        $hlava .= '<p class="zdroj"><b>Doplněno z feriálního dne:</b> ' . h(implode(', ', $t['doplneno'])) . '. Misál pro bohoslužbu slova doporučuje čtení ze dne; vlastní texty zadejte v kontrole.</p>';
    }
    if (!empty($p['pamatka_bez_stranky'])) {
        $hlava .= '<p class="chyba"><b>Pozor:</b> památka ' . h($p['nazev']) . ' nemá na liturgie.cz vlastní stránku. Vstupní modlitba, orace a čtení jsou feriální; vlastní texty doplňte z misálu.</p>';
    }
    $hlava .= '</header>';
    $out = [$hlava];
    if (!empty($p['vigilie'])) {
        // Vigilie začíná obřadem světla a křestní částí; tady je jen bohoslužba slova
        $out[] = sekceHtml('Úvodní obřady', tabulka([['pokyn', 'Obřad světla (velikonoční svíce, Exultet) a úvodní část vigilie podle misálu. Tady je bohoslužba slova.']]));
        $out[] = sekceHtml('Bohoslužba slova', tabulka(radkyVigilie($t)));
    } else {
        $out[] = sekceHtml('Úvodní obřady', tabulka(radkyUvod($p, $t)));
        $out[] = sekceHtml('Bohoslužba slova', tabulka(radkySlova($p, $t)));
    }
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
form.nastaveni { border: 1px solid #000; padding: 4pt 6pt; margin: 0 0 8pt; font-size: 10pt; }
form.nastaveni .radek { margin: 3pt 0; }
a.tlacitko { display: inline-block; border: 1px solid #000; padding: 1pt 6pt; text-decoration: none; color: #000; }
.krabice { border: 1px solid #000; padding: 6pt 8pt; margin: 8pt 0; font-size: 10pt; }
.krabice h2 { font-size: 12pt; margin: 0 0 4pt; }
@media print { form.nastaveni { display: none; } .nezobrazovat { display: none; } body { padding: 0; max-width: none; } a { color: #000; text-decoration: none; } }
CSS;

function stranka(array $p, array $cfg, bool $web = false, ?array $upravy = null, ?string $formular = null): string
{
    $t = uplatniUpravy(nactiTexty($p, $cfg), $upravy['texty'] ?? []);
    $t['zalm_cislo'] = zalmCislo($t['zalm']['citace'] ?? '');
    $t['korejs'] = $t['zalm_cislo'] !== '' ? korejsOdkaz($t['zalm']['citace'], $p['rok'], $p['nazev'], $cfg) : null;
    if (!empty($p['vigilie'])) {
        // každý žalm vigilie má vlastní nápěv na Korejsovi
        $t['zalmy_korejs'] = [];
        foreach ($t['cteni_vigilie'] ?? [] as $c) {
            $cislo = zalmCislo($c['zalm']['citace'] ?? '');
            if ($cislo === '') { continue; }
            $t['zalmy_korejs'][] = ['cislo' => $cislo, 'url' => korejsOdkaz($c['zalm']['citace'], '', $p['nazev'], $cfg)];
        }
    }
    $zakladni = preg_match('~^https?://~', $cfg['zdroj']) ? rtrim($cfg['zdroj'], '/') . '/' : 'https://m.liturgie.cz/misal/';
    $nadpis = 'Bohoslužba slova ' . cesDatum($p['datum']);
    $uvod = '<h1>' . h($nadpis) . '</h1>' . ($formular ?? '')
        . '<p class="legenda"><b>Misál:</b> <a href="' . h($zakladni) . '">' . h($zakladni) . '</a>, <b>kalendář:</b> <a href="' . h($zakladni . 'kalendar.htm') . '">' . h($zakladni . 'kalendar.htm') . '</a></p>'
        . ' ' . '<p class="legenda"><b>Zkratky:</b> <b>P</b> = předsedající (Vlasta nebo Honza, jsou-li přítomni, jinak jiný pověřený), <b>Ž</b> = žalmista (Honza nebo varhanice Lenka), <b>L</b> = lektor, <b>Vš</b> = všichni. Kurzívou jsou řádky, které se nečtou nahlas (pokyny a poznámky). Varianta A platí, když nejsou přítomni ani Vlasta, ani Honza; varianta B, když vede Vlasta nebo Honza.</p>';
    return "<!doctype html>\n<html lang=\"cs\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>" . h($nadpis) . "</title>\n<style>" . CSS . "</style>\n</head>\n<body>\n$uvod\n"
        . krabiceMse($p, $t, $web, $upravy, $cfg) . "\n" . bohosluzbaHtml($p, $t) . "\n</body>\n</html>\n";
}

/** Přepínače formuláře jako část adresy, aby kontrola a odeslání běžely se stejným nastavením. */
function nastaveniUrl(array $cfg): string
{
    return 'forma=1&nanebevstoupeni=' . $cfg['nanebevstoupeni'] . '&bozi_telo=' . $cfg['bozi_telo']
        . '&zformular=' . $cfg['zesnuli_formular'] . ($cfg['slavnosti_ve_vsedni_den'] ? '&vsedni=1' : '');
}

/**
 * Krabička bohoslužby: zdroj, kontrola (jen web), žalm s odkazem na Korejs a tisk.
 * Tisk se zpřístupní až po zaškrtnutí všech částí (odeslaná kontrola).
 */
function krabiceMse(array $p, array $t, bool $web, ?array $upravy, array $cfg): string
{
    $datum = $p['datum']->format('Y-m-d');
    $nazvy = castyUpravy(!empty($p['vigilie']));
    $tisk = false; $stav = '';
    if ($upravy !== null) {
        $ruc = array_map(fn($k) => $nazvy[$k][0], array_keys($upravy['texty']));
        $nove = array_map(fn($k) => $nazvy[$k][0], array_diff(array_keys($nazvy), array_keys($upravy['overeno']), !empty($t['cteni2']) ? [] : ['cteni2']));
        $tisk = $nove === [];
        $stav = '<p><b>Kontrola:</b> ručně upraveno: ' . h($ruc ? implode(', ', $ruc) : 'nic') . '. Neověřeno (nezaškrtnuto): ' . h($nove ? implode(', ', $nove) : 'nic') . '.</p>';
    }
    $r = ['<p><b>' . h(cesDatum($p['datum'])) . ': ' . h($p['nazev']) . '</b></p>'];
    if ($web) { $r[] = '<p><b>Tisk bude dostupný až po kontrole textů.</b></p>'; }
    $zdroj = '<a href="' . h((string)$t['zdroj_url']) . '" target="_blank" rel="noopener">' . h((string)$t['zdroj_url']) . '</a>';
    $r[] = '<p>Zdroj textů: ' . $zdroj . (!empty($p['pasije']) ? ' (včetně pašijí)' : '') . '</p>';
    if ($web) { $r[] = '<p><a class="tlacitko" href="' . h('?uprava=' . $datum . '&' . nastaveniUrl($cfg)) . '">Zkontrolovat texty</a></p>'; }
    if (!empty($t['zalmy_korejs'])) {
        $odkazy = array_map(fn($z) => $z['url'] !== null
            ? h('Žl ' . preg_replace('~^Žl?\s*~u', '', $z['cislo'])) . ' (<a href="' . h($z['url']) . '" target="_blank" rel="noopener">Korejs</a>)'
            : h($z['cislo']), $t['zalmy_korejs']);
        $r[] = '<p>Žalmy vigilie (zpívá Honza nebo varhanice Lenka): ' . implode(', ', $odkazy) . '.</p>';
    } elseif ($t['zalm_cislo'] !== '') {
        $zalm = '<p>Žalm ' . h($t['zalm_cislo']) . ' – zpívá Honza nebo varhanice Lenka. ';
        $zalm .= $t['korejs'] !== null
            ? '<a class="tlacitko" href="' . h($t['korejs']) . '" target="_blank" rel="noopener">Tisk žalmu (Korejs)</a>'
            : 'Nápěv na Korejsovi nenalezen, <a href="' . h(rtrim($cfg['korejs'], '/') . '/podlelekcionare.html') . '" target="_blank" rel="noopener">tabulka lekcionáře</a>.';
        $r[] = $zalm . '</p>';
    }
    $r[] = $stav;
    if ($web) { $r[] = '<p><button type="button" onclick="window.print()"' . ($tisk ? '' : ' disabled') . '>Tisk / PDF</button>' . ($tisk ? '' : ' (zpřístupní se po zaškrtnutí všech částí)') . '</p>'; }
    return '<div class="krabice nezobrazovat" id="kontrola">' . implode('', $r) . '<p><a href="#text">↓ Na text bohoslužby</a></p></div>';
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
function castyUpravy(bool $vigilie = false): array
{
    // Vigilie: osm čtení místo jednoho (1.–7. čtení a epištola), evangelium se kontroluje také
    if ($vigilie) {
        $vysl = [];
        for ($i = 1; $i <= 7; $i++) { $vysl["vig$i"] = ["$i. čtení", "$i. čtení"]; }
        $vysl['vigep'] = ['Epištola', null];
        $vysl['evangelium'] = ['Evangelium', 'evangelium'];
        return $vysl;
    }
    return [
        'vstupni' => ['Vstupní modlitba', 'vstupní modlitba'],
        'cteni1' => ['1. čtení', '1. čtení'],
        'zalm' => ['Žalm (jen číslo; text a nápěv jsou v Korejsovi)', 'žalm'],
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
        // vigilie: vig1–vig7 jsou 1.–7. čtení, vigep je epištola
        default => str_starts_with($klic, 'vig') ? ($t['cteni_vigilie'][$klic === 'vigep' ? 7 : (int)substr($klic, 3) - 1]['text'] ?? '') : '',
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
            default:
                if (str_starts_with($klic, 'vig')) {
                    $i = $klic === 'vigep' ? 7 : (int)substr($klic, 3) - 1;
                    $t['cteni_vigilie'][$i] = array_merge(['citace' => '', 'uvod' => ''], $t['cteni_vigilie'][$i] ?? [], ['text' => $text]);
                }
        }
        $popis = castyUpravy(true)[$klic][1] ?? null;
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
    foreach (array_keys(castyUpravy(true) + castyUpravy()) as $klic) {
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
    foreach (castyUpravy(!empty($p['vigilie'])) as $klic => [$nadpis]) {
        $auto = automatickyText($klic, $t);
        $casti .= '<section class="cast"><h3>' . h($nadpis) . '</h3>'
            . '<div class="auto"><b>Automatický text:</b><br>' . ($auto !== '' ? h($auto) : '<em>nenalezen</em>') . '</div>'
            . ($klic === 'zalm' ? '' : '<label>Vlastní text (prázdné = použije se automatický):<textarea name="ov[' . $klic . ']" rows="5"></textarea></label>')
            . '<label><input type="checkbox" name="ok[' . $klic . ']" value="1"> Sedí, text jsem zkontroloval</label></section>';
    }
    return "<!doctype html>\n<html lang=\"cs\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Kontrola textů $datum</title>\n<style>" . CSS . UPRAVA_CSS . "</style>\n</head>\n<body>\n"
        . '<h1>Kontrola textů: ' . h(cesDatum($d)) . '</h1><p class="datum">' . h($p['nazev']) . ' · ' . h(oznaceniRoku($p)) . '</p>'
        . '<p class="zdroj">Zdroj: <a href="' . h((string)$t['zdroj_url']) . '" target="_blank" rel="noopener">' . h((string)$t['zdroj_url']) . '</a>. Porovnejte každou část se zdrojem. Co sedí, zaškrtněte. Co nesedí, napište do políčka „Vlastní text“. Texty se nikam neukládají, použijí se jen v bohoslužbě, kterou tlačítkem vytvoříte.</p>'
        . '<form method="post" action="' . h('?den=' . $datum . '&' . nastaveniUrl($cfg)) . '"><p><button type="button" onclick="document.querySelectorAll(\'form input[type=checkbox]\').forEach(function(c){c.checked=true;})">Všechno sedí</button></p>' . $casti
        . '<p><button>Vytvořit bohoslužbu s těmito texty (k tisku)</button> <a href="' . h('?den=' . $datum . '&' . nastaveniUrl($cfg)) . '">Bohoslužbu bez úprav</a></p></form>'
        . "\n</body>\n</html>\n";
}

// ======================= SPUŠTĚNÍ =======================
/** Webový formulář s přepínači (při tisku se skryje). */
function formular(array $cfg, string $den): string
{
    $sel = fn(string $k, string $h) => $cfg[$k] === $h ? ' selected' : '';
    return '<form method="get" class="nastaveni"><input type="hidden" name="forma" value="1">'
        . '<p><b>Nastavení</b></p>'
        . '<div class="radek">Den bohoslužby <input type="date" name="den" value="' . h($den) . '"></div>'
        . '<div class="radek">Nanebevstoupení <select name="nanebevstoupeni"><option value="ctvrtek"' . $sel('nanebevstoupeni', 'ctvrtek') . '>ve čtvrtek</option><option value="nedele"' . $sel('nanebevstoupeni', 'nedele') . '>v neděli</option></select></div>'
        . '<div class="radek">Boží Tělo <select name="bozi_telo"><option value="ctvrtek"' . $sel('bozi_telo', 'ctvrtek') . '>ve čtvrtek</option><option value="nedele"' . $sel('bozi_telo', 'nedele') . '>v neděli</option></select></div>'
        . '<div class="radek">Vzpomínka na zemřelé (2. 11.): formulář <select name="zformular">' . implode('', array_map(fn($n) => '<option value="' . $n . '"' . ($cfg['zesnuli_formular'] === $n ? ' selected' : '') . '>' . $n . '. mešní formulář</option>', [1, 2, 3])) . '</select></div>'
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
    foreach (['nanebevstoupeni', 'bozi_telo'] as $k) {
        if (isset($arg[$k]) && in_array($arg[$k], ['ctvrtek', 'nedele'], true)) { $cfg[$k] = $arg[$k]; }
    }
    if (isset($arg['zformular']) && in_array((int)$arg['zformular'], [1, 2, 3], true)) { $cfg['zesnuli_formular'] = (int)$arg['zformular']; }
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
    $upravy = null; $den = null;
    try {
        if ($platne($arg['den'] ?? null)) {
            $den = new DateTimeImmutable($arg['den']);
        } else {
            // Bez data: následující neděle (dnešní, pokud je dnes neděle a 'vcetne_dnes')
            $den = nasledujiciNedele(new DateTimeImmutable('today'), $cfg['vcetne_dnes']);
        }
        $p = popisDne($den, $cfg + ['_vsedni' => ['nanebevstoupeni', 'bozi_telo']]);
        if ($p === null) { throw new RuntimeException('Tento den není nedělí ani nastavenou slavností.'); }
        $p['datum'] = $den;
        if ($web && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $upravy = zFormulare($_POST); }
        $html = stranka($p, $cfg, $web, $upravy, $web ? formular($cfg, $den->format('Y-m-d')) : null);
    } catch (Throwable $e) {
        if ($web) { http_response_code(500); }
        // Na webu zůstane formulář, aby šlo zvolit jiný den
        $html = '<!doctype html><meta charset="utf-8"><p>Chyba: ' . htmlspecialchars($e->getMessage()) . '</p>'
            . ($web ? formular($cfg, $den ? $den->format('Y-m-d') : '') : '');
    }
    if (!$web && !empty($arg['vystup'])) { file_put_contents($arg['vystup'], $html); fwrite(STDERR, "Uloženo do {$arg['vystup']}\n"); return; }
    if ($web) { header('Content-Type: text/html; charset=utf-8'); }
    echo $html;
}

// ======================= TESTY (vestavěné) =======================
// Spuštění: php bohosluzba.php --test. Ověřuje kalendář, rozbor stránek a bohoslužby na syntetických ukázkách
// (žádný text z liturgie.cz). Ukázky se zapíšou do dočasné složky a po testech se smažou.

function jeRovno($pop, $skut, $oc) { global $ok, $chyb; if ($skut === $oc) { $ok++; } else { $chyb++; echo "CHYBA: $pop: čekáno ", var_export($oc, true), ", je ", var_export($skut, true), "\n"; } }

/** Syntetické ukázky stránek (relativně ke složce testů). */
function testovaciSoubory(): array
{
    return [
        'korejs/podlelekcionare.html' => <<<'HTML'
<html><head><meta charset="utf-8"></head><body><table>
<tr><td><a href="zalmy/TestPs080_05.pdf">TestPs080_05</a></td><td>ID_T1</td><td>Žl 80(79)</td><td>9+12.13-14.15-16.19-20</td><td>1A</td><td>1</td><td>Mezidobí</td><td>27. neděle v mezidobí</td><td>A</td><td>1</td><td>4</td><td>1</td></tr>
<tr><td><a href="zalmy/TestPs080_03.pdf">TestPs080_03</a></td><td>ID_T2</td><td>Žl 80(79)</td><td>2ac+3b.15-16.18-19</td><td>1B</td><td>2</td><td>Advent</td><td>1. neděle adventní</td><td>B</td><td>1</td><td>2</td><td>2</td></tr>
<tr><td><a href="zalmy/TestPs080_03c.pdf">TestPs080_03c</a></td><td>ID_T3</td><td>Žl 80(79)</td><td>2ac+3b.15-16.18-19</td><td>1C</td><td>3</td><td>Advent</td><td>4. neděle adventní</td><td>C</td><td>1</td><td>2</td><td>3</td></tr>
</table></body></html>
HTML,

        'misal/01advent/01_01.htm' => <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pondělí po 1. neděli adventní - testovací data</title>
</head>
<body>
<h3>Pondělí po 1. neděli adventní</h3>
<h4>Vstupní modlitba</h4>
<p>Testovací vstupní modlitba adventního dne.</p>
<h4>1. čtení - Test 2,1-5</h4>
<p><em>Podtitul testovacího čtení.</em></p>
<p>Čtení z testovací knihy.</p>
<p>Testovací hlavní čtení adventního dne.</p>
<p class="red">Čte-li se toto čtení na 1. neděli adventní (v ročním cyklu A), může se dnes vzít:</p>
<p onclick="zobrazSkryj('oddilA')"><button>1. čtení v cyklu A</button></p>
<div id="oddilA" class="skryvany">
<H4>1. čtení - Test 4,2-6</H4>
<p>Testovací alternativní čtení, které se nemá číst jako hlavní.</p>
</div>
<h4>Mezizpěv - Žl 122,1-2</h4>
<p><em>Testovací refrén adventní.</em></p>
<p>Testovací sloka adventní.</p>
<h4>Zpěv před evangeliem</h4>
<p>Aleluja. Testovací verš adventní. Aleluja.</p>
<h4>Evangelium - Test 8,5-11</h4>
<p>Čtení ze svatého evangelia.</p>
<p>Testovací evangelium adventní.</p>
<h4>Modlitba po přijímání</h4>
<p>Testovací modlitba po přijímání adventní.</p>
</body>
</html>
HTML,

        'misal/04triduum/04vigilie_2.htm' => <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Velikonoční vigilie - testovací data</title>
</head>
<body>
<h3>Velikonoční vigilie</h3>
<h3>Bohoslužba slova</h3>
<h4>1. čtení – Test 1,1-2</h4>
<p>Čtení z testovací knihy.</p>
<p>Testovací první čtení vigilie.</p>
<h4>Mezizpěv – Žl 104,1-2a.5-6</h4>
<p><em>Testovací refrén vigilie.</em></p>
<p>Testovací sloka vigilie.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po prvním čtení.</p>
<h4>2. čtení – Test 2,1-2</h4>
<p>Čtení z testovací knihy.</p>
<p>Testovací druhé čtení vigilie.</p>
<h4>Mezizpěv – Žl 16,5.8-9</h4>
<p>Testovací sloka druhého žalmu.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po druhém čtení.</p>
<h4>3. čtení – Test 3,1-2</h4>
<p>Testovací třetí čtení vigilie.</p>
<h4>Mezizpěv – Žl 19,8.9</h4>
<p>Testovací sloka třetího žalmu.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po třetím čtení.</p>
<h4>4. čtení – Test 4,1-2</h4>
<p>Testovací čtvrté čtení vigilie.</p>
<h4>Mezizpěv – Žl 30,2.4</h4>
<p>Testovací sloka čtvrtého žalmu.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po čtvrtém čtení.</p>
<h4>5. čtení – Test 5,1-2</h4>
<p>Testovací páté čtení vigilie.</p>
<h4>Mezizpěv – Žl 12,4</h4>
<p>Testovací sloka pátého žalmu.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po pátém čtení.</p>
<h4>6. čtení – Test 6,1-2</h4>
<p>Testovací šesté čtení vigilie.</p>
<h4>Mezizpěv – Žl 42,3.5</h4>
<p>Testovací sloka šestého žalmu.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po šestém čtení.</p>
<h4>7. čtení – Test 7,1-2</h4>
<p>Testovací sedmé čtení vigilie.</p>
<h4>Mezizpěv – Žl 51,12</h4>
<p>Testovací sloka sedmého žalmu.</p>
<h4>Modlitba</h4>
<p>Testovací modlitba po sedmém čtení.</p>
<h4>Epištola – Test 8,3-11</h4>
<p>Testovací epištola vigilie.</p>
<h4>Mezizpěv – Žl 118,1-2</h4>
<p>Testovací sloka závěrečného žalmu.</p>
<button>Evangelium v roce A</button>
<h4>Evangelium v cyklu A – Test 9,1-10</h4>
<p>Testovací evangelium roku A.</p>
<button>Evangelium v roce B</button>
<h4>Evangelium v cyklu B – Test 9,11-20</h4>
<p>Testovací evangelium roku B.</p>
<button>Evangelium v roce C</button>
<h4>Evangelium v cyklu C – Test 9,21-30</h4>
<p>Testovací evangelium roku C.</p>
<button>Následuje křestní bohoslužba</button>
</body>
</html>
HTML,

        'misal/06mezidobi/28_00.htm' => base64_decode('PGh0bWw+PGhlYWQ+PG1ldGEgaHR0cC1lcXVpdj0iQ29udGVudC1UeXBlIiBjb250ZW50PSJ0ZXh0L2h0bWw7IGNoYXJzZXQ9d2luZG93cy0xMjUwIj48dGl0bGU+eDwvdGl0bGU+PC9oZWFkPjxib2R5PjxoMz4yOC4gTkVEzExFIFYgTUVaSURPQs08L2gzPjxoND5Wc3R1cG7tIGFudGlmb25hPC9oND48cD5BbnRpZm9uYS48L3A+PGg0PlZzdHVwbu0gbW9kbGl0YmE8L2g0PjxwPlRlc3RvdmFj7SB2c3R1cG7tIG1vZGxpdGJhIDI4LiBuZWTsbGUuIFNrcnplIG5hmmVobyBQ4W5hLjwvcD48cD5BbWVuLjwvcD48aDQ+yHRlbu0geiBQ7XNtYSBwb2RsZSByb+hu7WhvIGN5a2x1PC9oND48aDQ+yHRlbu0geiBQ7XNtYSB2IHJvY2UgQTwvaDQ+CjxoND4xLiDodGVu7TwvaDQ+PHA+SXogMSwxLTI8L3A+PHA+PGk+MS4g6HRlbu08L2k+PC9wPjxwPsh0ZW7tIHoga25paHkgdGVzdG92YWPtIEE8L3A+PHA+VGVzdG92YWPtIHBydm7tIOh0ZW7tIEEsIHbsdGEgamVkbmEuIFbsdGEgZHbsLjwvcD48cD5TbG92byBCb57tLjwvcD4KPGg0Pk1leml6cOx2PC9oND48cD6ObCAyMyAoMjIpLCAxLTNhLjNiLTQuNS02PC9wPjxwPjxpPltUZXN0b3ZhY+0gb2Rwb3bs7yBBLl08L2k+PC9wPjxwPlNsb2thIGplZG5hIEEsPGJyPmRydWj9IPjhZGVrLjwvcD48cD5TbG9rYSBkduwgQSw8YnI+ZHJ1aP0g+OFkZWsuPC9wPgo8aDQ+Mi4g6HRlbu08L2g0PjxwPkZpbCA0LDYtOTwvcD48cD7IdGVu7SB6IGxpc3R1IHRlc3RvdmFj7WhvIEE8L3A+PHA+VGVzdG92YWPtIGRydWjpIOh0ZW7tIEEuPC9wPjxwPlNsb3ZvIEJvnu0uPC9wPgo8aDQ+WnDsdiBw+GVkIGV2YW5nZWxpZW08L2g0PjxwPkVmIDEsMTc8L3A+PHA+QWxlbHVqYS4gVGVzdG92YWPtIHZlcpogQS4gQWxlbHVqYS48L3A+CjxoND5FdmFuZ2VsaXVtPC9oND48cD5NdCAyMSwzMy00MzwvcD48cD5TbG92YSBzdmF06WhvIGV2YW5nZWxpYSBwb2RsZSBUZXN0YSBBPC9wPjxwPlRlc3RvdmFj7SBldmFuZ2VsaXVtIEEuPC9wPjxwPlNsb3ZvIFDhbuwuPC9wPgo8aDQ+yHRlbu0geiBQ7XNtYSB2IHJvY2UgQjwvaDQ+CjxoND4xLiDodGVu7TwvaDQ+PHA+SXogMSwxLTI8L3A+PHA+PGk+MS4g6HRlbu08L2k+PC9wPjxwPsh0ZW7tIHoga25paHkgdGVzdG92YWPtIEI8L3A+PHA+VGVzdG92YWPtIHBydm7tIOh0ZW7tIEIsIHbsdGEgamVkbmEuIFbsdGEgZHbsLjwvcD48cD5TbG92byBCb57tLjwvcD4KPGg0Pk1leml6cOx2PC9oND48cD6ObCAyMyAoMjIpLCAxLTNhLjNiLTQuNS02PC9wPjxwPjxpPltUZXN0b3ZhY+0gb2Rwb3bs7yBCLl08L2k+PC9wPjxwPlNsb2thIGplZG5hIEIsPGJyPmRydWj9IPjhZGVrLjwvcD48cD5TbG9rYSBkduwgQiw8YnI+ZHJ1aP0g+OFkZWsuPC9wPgo8aDQ+Mi4g6HRlbu08L2g0PjxwPkZpbCA0LDYtOTwvcD48cD7IdGVu7SB6IGxpc3R1IHRlc3RvdmFj7WhvIEI8L3A+PHA+VGVzdG92YWPtIGRydWjpIOh0ZW7tIEIuPC9wPjxwPlNsb3ZvIEJvnu0uPC9wPgo8aDQ+WnDsdiBw+GVkIGV2YW5nZWxpZW08L2g0PjxwPkVmIDEsMTc8L3A+PHA+QWxlbHVqYS4gVGVzdG92YWPtIHZlcpogQi4gQWxlbHVqYS48L3A+CjxoND5FdmFuZ2VsaXVtPC9oND48cD5NdCAyMSwzMy00MzwvcD48cD5TbG92YSBzdmF06WhvIGV2YW5nZWxpYSBwb2RsZSBUZXN0YSBCPC9wPjxwPlRlc3RvdmFj7SBldmFuZ2VsaXVtIEIuPC9wPjxwPlNsb3ZvIFDhbuwuPC9wPgo8aDQ+yHRlbu0geiBQ7XNtYSB2IHJvY2UgQzwvaDQ+CjxoND4xLiDodGVu7TwvaDQ+PHA+SXogMSwxLTI8L3A+PHA+PGk+MS4g6HRlbu08L2k+PC9wPjxwPsh0ZW7tIHoga25paHkgdGVzdG92YWPtIEM8L3A+PHA+VGVzdG92YWPtIHBydm7tIOh0ZW7tIEMsIHbsdGEgamVkbmEuIFbsdGEgZHbsLjwvcD48cD5TbG92byBCb57tLjwvcD4KPGg0Pk1leml6cOx2PC9oND48cD6ObCAyMyAoMjIpLCAxLTNhLjNiLTQuNS02PC9wPjxwPjxpPltUZXN0b3ZhY+0gb2Rwb3bs7yBDLl08L2k+PC9wPjxwPlNsb2thIGplZG5hIEMsPGJyPmRydWj9IPjhZGVrLjwvcD48cD5TbG9rYSBkduwgQyw8YnI+ZHJ1aP0g+OFkZWsuPC9wPgo8aDQ+Mi4g6HRlbu08L2g0PjxwPkZpbCA0LDYtOTwvcD48cD7IdGVu7SB6IGxpc3R1IHRlc3RvdmFj7WhvIEM8L3A+PHA+VGVzdG92YWPtIGRydWjpIOh0ZW7tIEMuPC9wPjxwPlNsb3ZvIEJvnu0uPC9wPgo8aDQ+WnDsdiBw+GVkIGV2YW5nZWxpZW08L2g0PjxwPkVmIDEsMTc8L3A+PHA+QWxlbHVqYS4gVGVzdG92YWPtIHZlcpogQy4gQWxlbHVqYS48L3A+CjxoND5FdmFuZ2VsaXVtPC9oND48cD5NdCAyMSwzMy00MzwvcD48cD5TbG92YSBzdmF06WhvIGV2YW5nZWxpYSBwb2RsZSBUZXN0YSBDPC9wPjxwPlRlc3RvdmFj7SBldmFuZ2VsaXVtIEMuPC9wPjxwPlNsb3ZvIFDhbuwuPC9wPgo8aDQ+Vnl6buFu7SB27XJ5PC9oND48cD5W7PjtbS4uLjwvcD48aDQ+TW9kbGl0YmEgbmFkIGRhcnk8L2g0PjxwPkRhcnkuPC9wPjxoND5Nb2RsaXRiYSBwbyBw+Glq7W3hbu08L2g0PjxwPlRlc3RvdmFj7SBtb2RsaXRiYSBwbyBw+Glq7W3hbu0uIFNrcnplIEtyaXN0YS48L3A+PHA+QW1lbi48L3A+PC9ib2R5PjwvaHRtbD4='),

        'misal/06mezidobi/29_01.htm' => <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>29. týden v mezidobí - testovací data</title>
</head>
<body>
<h3>Pondělí po 29. neděli v mezidobí</h3>
<h4>Vstupní antifona – Žl 1,1</h4>
<p><em>Testovací vstupní antifona.</em></p>
<h4>Vstupní modlitba</h4>
<p>Testovací vstupní modlitba pro všední den. Prosíme o to skrze tvého Syna ...</p>
<h4>První čtení a žalm podle ročního cyklu</h4>
<p onclick="zobrazSkryj('oddil1')"><button>Roční cyklus 1</button></p>
<div id="oddil1" class="skryvany">
<h4>1. čtení – Test 1,1-2</h4>
<p><em>Čtení z testovací knihy.</em></p>
<p>Testovací první čtení cyklu jedna. Věta dvě.</p>
<h4>Mezizpěv – Lk 1,69-70</h4>
<p><em>Testovací refrén cyklu jedna.</em></p>
<p>Testovací sloka cyklu jedna.</p>
</div>
<p onclick="zobrazSkryj('oddil2')"><button>Roční cyklus 2</button></p>
<div id="oddil2" class="skryvany">
<h4>1. čtení – Test 2,1-2</h4>
<p><em>Čtení z testovací knihy.</em></p>
<p>Testovací první čtení cyklu dva. Věta dvě.</p>
<h4>Mezizpěv – Žl 100,2.3</h4>
<p><em>Testovací refrén cyklu dva.</em></p>
<p>Testovací sloka cyklu dva.</p>
</div>
<h4>Zpěv před evangeliem – Mt 5,3</h4>
<p>Aleluja. Testovací verš před evangeliem. Aleluja.</p>
<h4>Evangelium – Test 3,1-2</h4>
<p>Čtení ze svatého evangelia podle Testa.</p>
<p>Testovací evangelium všedního dne. Věta dvě.</p>
<h4>Modlitba nad dary</h4>
<p>Testovací modlitba nad dary.</p>
<h4>Modlitba po přijímání</h4>
<p>Testovací modlitba po přijímání pro všední den.</p>
</body>
</html>
HTML,

        'misal/06mezidobi/NST.htm' => <<<'HTML'
<html><head><meta charset="utf-8"></head><body><h3>Slavnost Nejsvětější Trojice</h3><h4>Vstupní modlitba</h4><p>Test vstupní slavnosti.</p><p>Amen.</p>
<h4>Čtení z Písma podle ročního cyklu</h4>
<h4>1. čtení – Ex 34,4b-6</h4><p>Čtení z knihy testovací A.</p><p>Čtení A, věta jedna.</p><p>Slovo Boží.</p>
<h4>Mezizpěv – Dan 3,52</h4><p><i>[Odpověď slavnost A.]</i></p><p>Sloka slavnost A.</p>
<h4>2. čtení – 2 Kor 13,11</h4><p>Čtení z druhého listu testovacího A.</p><p>Druhé čtení A.</p>
<h4>Zpěv před evangeliem – srov. Zj 1,8</h4><p>Aleluja. Verš slavnost A. Aleluja.</p>
<h4>Evangelium – Jan 3,16-18</h4><p>Slova svatého evangelia podle Jana.</p><p>Evangelium slavnost A.</p>
<h4>1. čtení – Dt 4,32-34</h4><p>Čtení z knihy testovací B.</p><p>Čtení B, věta jedna.</p><p>Slovo Boží.</p>
<h4>Mezizpěv – Žl 33,4-5</h4><p><i>[Odpověď slavnost B.]</i></p><p>Sloka slavnost B.</p>
<h4>2. čtení – Řím 8,14</h4><p>Čtení z listu testovacího B.</p><p>Druhé čtení B.</p>
<h4>Zpěv před evangeliem – srov. Zj 1,8</h4><p>Aleluja. Verš slavnost B. Aleluja.</p>
<h4>Evangelium – Mt 28,16-20</h4><p>Slova svatého evangelia podle Matouše.</p><p>Evangelium slavnost B.</p>
<h4>1. čtení – Př 8,22-31</h4><p>Čtení z knihy testovací C.</p><p>Čtení C, věta jedna.</p><p>Slovo Boží.</p>
<h4>Mezizpěv – Žl 8,4-5</h4><p><i>[Odpověď slavnost C.]</i></p><p>Sloka slavnost C.</p>
<h4>2. čtení – Řím 5,1-5</h4><p>Čtení z listu testovacího C.</p><p>Druhé čtení C.</p>
<h4>Zpěv před evangeliem – Srov. Zj 1,8</h4><p>Aleluja. Verš slavnost C. Aleluja.</p>
<h4>Evangelium – Jan 16,12-15</h4><p>Slova svatého evangelia podle Jana.</p><p>Evangelium slavnost C.</p>
<h4>Odpověď na Boží slovo</h4><p>Vyznání víry.</p><h4>Modlitba nad dary</h4><p>Dary.</p>
<h4>Modlitba po přijímání</h4><p>Test po přijímání slavnosti. Skrze Krista.</p><p>Amen.</p></body></html>
HTML,

        'misal/08sanctoral/11_01.htm' => <<<'HTML'
<html><head><meta charset="utf-8"></head><body><h3>Slavnost všech svatých</h3><h4>Vstupní modlitba</h4><p>Testovací modlitba Všech svatých.</p><p>Amen.</p>
<h4>1. čtení - Zj 7,2-4.9-14</h4><p>Čtení z knihy Zjevení</p><p>Test čtení svatých.</p><p>Slovo Boží.</p>
<h4>Mezizpěv - Žl 24 (23), 1-2.3-4ab.5-6</h4><p><i>Toto je pokolení těch, kdo hledají tvou tvář.</i></p><p>Hospodinova je země,<br>i vše, co ji plní.</p>
<h4>2. čtení - 1 Jan 3,1-3</h4><p>Čtení z prvního listu svatého apoštola Jana</p><p>Test 2. čtení.</p><p>Slovo Boží.</p>
<h4>Zpěv před evangeliem - Mt 11,28</h4><p>Aleluja. Pojďte ke mně, všichni. Aleluja.</p>
<h4>Evangelium – Mt 5,1-12a</h4><p>Slova svatého evangelia podle Matouše</p><p>Test evangelium svatých.</p><p>Slovo Páně.</p>
<h4>Modlitba po přijímání</h4><p>Test po přijímání svatých.</p><p>Amen.</p></body></html>
HTML,

        'misal/08sanctoral/11_02.htm' => <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Vzpomínka na všechny věrné zemřelé - testovací data</title>
</head>
<body>
<h3>Vzpomínka na všechny věrné zemřelé</h3>
<h4>Mešní formuláře</h4>
<h4>1. mešní formulář</h4>
<h4>Vstupní antifona – Test 1,1</h4>
<h4>Vstupní modlitba</h4>
<p>Testovací vstupní modlitba prvního formuláře.</p>
<h4>Modlitba nad dary</h4>
<p>Testovací modlitba nad dary prvního formuláře.</p>
<h4>Modlitba po přijímání</h4>
<p>Testovací modlitba po přijímání prvního formuláře.</p>
<h4>2. mešní formulář</h4>
<h4>Vstupní antifona – Test 2,1</h4>
<h4>Vstupní modlitba</h4>
<p>Testovací vstupní modlitba druhého formuláře.</p>
<h4>Modlitba nad dary</h4>
<p>Testovací modlitba nad dary druhého formuláře.</p>
<h4>Modlitba po přijímání</h4>
<p>Testovací modlitba po přijímání druhého formuláře.</p>
<h4>3. mešní formulář</h4>
<h4>Vstupní modlitba</h4>
<p>Testovací vstupní modlitba třetího formuláře.</p>
<h4>Modlitba po přijímání</h4>
<p>Testovací modlitba po přijímání třetího formuláře.</p>
<h4>Preface</h4>
<h4>1. čtení – Test 9,1-2</h4>
<p>Testovací čtení vzpomínky.</p>
</body>
</html>
HTML,

        // pokyny, poznámky a druhá (nezvolená) varianta modlitby/čtení, jak je skutečně sází liturgie.cz:
        // červené "O: Amen."/"Nebo:", třída "m" pro editorské poznámky a zkrácené zakončení "Skrze tvého Syna …"
        'misal/03pust/00_07.htm' => <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pokyny a druhá varianta - testovací data</title>
</head>
<body>
<h3>Testovací den s pokyny</h3>
<h4>Vstupní modlitba</h4>
<p class="red">(Neříká se <em>Modleme se</em>)</p>
<p>Testovací vstupní modlitba, první varianta. Skrze tvého Syna ...<br>
<span class="red">O:</span> Amen.</p>
<p class="red">Nebo:</p>
<p name="m" class="m">Poznámka, která se nemá číst.</p>
<p>Testovací vstupní modlitba, druhá varianta, se nemá použít.</p>
<h4>1. čtení – Test 3,1-2</h4>
<p name="m" class="m">Poznámka před podtitulem čtení.</p>
<p><em>Podtitul testovacího čtení s pokyny.</em></p>
<p>Čtení z testovací knihy.</p>
<p>Testovací čtení s pokynem na konci.</p>
<p class="red">Pokyn, který nepatří do čtení.</p>
<h4>Mezizpěv – Žl 1,1</h4>
<p><em>Testovací refrén.</em></p>
<p>Testovací sloka jedna.</p>
<p><span class="red">Nebo: </span><em>Aleluja.</em></p>
<p>Testovací sloka jiného žalmu, která se nemá použít.</p>
<h4>Zpěv před evangeliem</h4>
<p>Aleluja. Testovací verš. Aleluja.</p>
<h4>Evangelium – Test 4,1-2</h4>
<p>Slova svatého evangelia.</p>
<p>Testovací evangelium.</p>
<h4>Modlitba po přijímání</h4>
<p>Testovací modlitba po přijímání. Neboť ty žiješ a kraluješ s Bohem Otcem v jednotě ...</p>
</body>
</html>
HTML,
    ];
}

/** Smaže složku i s obsahem (dočasné ukázky testů). */
function smazSlozku(string $dir): void
{
    if (!is_dir($dir)) { return; }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $cesta = "$dir/$f";
        if (is_dir($cesta)) { smazSlozku($cesta); } else { @unlink($cesta); }
    }
    @rmdir($dir);
}

function spustTesty(): int
{
    global $CFG, $ok, $chyb;
    $ok = 0; $chyb = 0;
    $tmp = sys_get_temp_dir() . '/bohosluzba-test-' . getmypid();
    foreach (testovaciSoubory() as $cesta => $obsah) {
        $plna = "$tmp/$cesta";
        if (!is_dir(dirname($plna))) { mkdir(dirname($plna), 0777, true); }
        file_put_contents($plna, $obsah);
    }
    $cfg = $CFG;
    $cfg['svatky_ve_vsedni_den'] = ['12-25','01-01','01-06','03-19','03-25','06-24','06-29','07-05','08-15','09-28','11-01','11-02','12-08'];
    $cfg['zdroj'] = "$tmp/misal";
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
$kc = $cfg; $kc['korejs'] = "$tmp/korejs/";
jeRovno('korejs: 27. neděle A', korejsOdkaz('Žl 80 (79), 9+12.13-14.15-16.19-20', 'A', '27. neděle v mezidobí', $kc), "$tmp/korejs/zalmy/TestPs080_05.pdf");
jeRovno('korejs: 1. adventní B', korejsOdkaz('Žl 80 (79), 2ac+3b.15-16.18-19', 'B', '1. neděle adventní', $kc), "$tmp/korejs/zalmy/TestPs080_03.pdf");
jeRovno('korejs: 4. adventní C', korejsOdkaz('Žl 80 (79), 2ac+3b.15-16.18-19', 'C', '4. neděle adventní', $kc), "$tmp/korejs/zalmy/TestPs080_03c.pdf");
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
jeRovno('bohoslužba: odkaz na kontrolu nese nastavení', str_contains($html, 'href="?uprava=2026-10-18&amp;forma=1&amp;nanebevstoupeni=ctvrtek&amp;bozi_telo=ctvrtek&amp;zformular=1&amp;vsedni=1"'), true);
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
// Vzpomínka na zemřelé: formulář 1–3 určuje vstupní modlitbu a modlitbu po přijímání
$zv = popisDne($d('2026-11-02'), $kc); $zv['datum'] = $d('2026-11-02');
jeRovno('zemřelí: formulář 1 (výchozí)', nactiTexty($zv, $kc)['vstupni'], 'Testovací vstupní modlitba prvního formuláře.');
$kz = $kc; $kz['zesnuli_formular'] = 2; $zv2 = nactiTexty($zv, $kz);
jeRovno('zemřelí: formulář 2', [$zv2['vstupni'], $zv2['po_prijimani'], $zv2['formular']], ['Testovací vstupní modlitba druhého formuláře.', 'Testovací modlitba po přijímání druhého formuláře.', 2]);
$kz['zesnuli_formular'] = 3; jeRovno('zemřelí: formulář 3 bez nadpisu nad dary', nactiTexty($zv, $kz)['vstupni'], 'Testovací vstupní modlitba třetího formuláře.');
jeRovno('zemřelí: čtení nejsou z formuláře', nactiTexty($zv, $kc)['cteni1'] ?? null, null);
// Adventní všední den: hlavní čtení je první, volitelné „v cyklu A“ se nebere, „Čte-li se …“ se neukáže
$pa = popisDne($d('2026-11-30'), $cfgDny); $pa['datum'] = $d('2026-11-30');
$ta = nactiTexty($pa, $kc);
jeRovno('advent: hlavní 1. čtení', $ta['cteni1']['citace'], 'Test 2,1-5');
jeRovno('advent: bez volitelného čtení', str_contains($ta['cteni1']['text'] ?? '', 'alternativní'), false);
jeRovno('advent: bez věty „Čte-li se“', str_contains($ta['cteni1']['text'] ?? '', 'Čte-li'), false);
jeRovno('advent: hlavní text 1. čtení', str_contains($ta['cteni1']['text'] ?? '', 'Testovací hlavní čtení'), true);
jeRovno('advent: verš bez Aleluja', $ta['verš']['bez_aleluja'] ?? null, 'Testovací verš adventní.');
jeRovno('advent: žalm a evangelium', [$ta['zalm']['citace'] ?? null, $ta['evangelium']['citace'] ?? null], ['Žl 122,1-2', 'Test 8,5-11']);
// Pokyny, poznámky (třída „m“) a druhá varianta modlitby/čtení/žalmu se nevezmou; zkrácené zakončení se doplní
$pp = ['nazev' => 'Testovací den s pokyny', 'odkaz' => ['slozka' => '03pust', 'soubor' => '00_07.htm'], 'hodnost' => 'ferie', 'obdobi' => 'pust', 'rok' => '', 'datum' => $d('2026-02-25')];
$tp = nactiTexty($pp, $kc);
jeRovno('pokyny: vstupní bez „(Neříká se)“ a bez druhé varianty', $tp['vstupni'],
    'Testovací vstupní modlitba, první varianta. Skrze tvého Syna Ježíše Krista, našeho Pána, neboť on s tebou v jednotě Ducha svatého žije a kraluje po všechny věky věků.');
jeRovno('pokyny: čtení bez poznámky před podtitulem a bez pokynu na konci', $tp['cteni1']['text'] ?? null, 'Testovací čtení s pokynem na konci.');
jeRovno('pokyny: žalm bez druhé varianty za „Nebo:“', $tp['zalm']['sloky'] ?? null, ['Testovací sloka jedna.']);
jeRovno('pokyny: modlitba po přijímání se zkráceným zakončením na Syna', $tp['po_prijimani'],
    'Testovací modlitba po přijímání. Neboť ty žiješ a kraluješ s Bohem Otcem v jednotě Ducha svatého po všechny věky věků.');
// Stránka všedního dne: bez vyznání a Gloria, s označením feriálního cyklu
set_error_handler(function (int $no, string $str) { throw new ErrorException($str, 0, $no); });
$hf = stranka($pf, $kc);
restore_error_handler();
jeRovno('ferie: stránka bez vyznání víry', str_contains($hf, 'Věřím v jednoho Boha'), false);
jeRovno('ferie: stránka bez Gloria', str_contains($hf, 'Sláva na výsostech'), false);
jeRovno('ferie: stránka s feriálním cyklem', str_contains($hf, 'feriální cyklus II'), true);
    smazSlozku($tmp);
    // Výchozí den bez data: následující neděle
    jeRovno('výchozí den: úterý → neděle', nasledujiciNedele($d('2026-10-06'), false)->format('Y-m-d'), '2026-10-11');
    jeRovno('výchozí den: neděle bez dnešku → za týden', nasledujiciNedele($d('2026-10-04'), false)->format('Y-m-d'), '2026-10-11');
    jeRovno('výchozí den: neděle s dneškem → dnes', nasledujiciNedele($d('2026-10-04'), true)->format('Y-m-d'), '2026-10-04');
    jeRovno('výchozí den: pondělí s dneškem → neděle', nasledujiciNedele($d('2026-10-05'), true)->format('Y-m-d'), '2026-10-11');
    // Poznámka z webu se nedostane do citace; kantikum bez čísla vypíše text, žalm s číslem jde na Korejs
    jeRovno('citace bez poznámky z webu', ocistiCitaci('Sk 1,12-14 (naznačené ze společných textů)'), 'Sk 1,12-14');
    jeRovno('citace žalmu bez poznámky', ocistiCitaci('Lk 1,46-48a.48b-49 (naznačený ze společných textů)'), 'Lk 1,46-48a.48b-49');
    jeRovno('kantikum vypíše refrén a sloky', radkyZalmu(['citace' => 'Lk 1,46-55', 'refren' => 'Refrén.', 'sloky' => ['Sloka jedna.']], 'Lk 1,46-55'),
        [['pokyn', 'Kantikum (Lk 1,46-55). Zpívá Honza nebo varhanice Lenka. Lid sedí.'], ['Vš', 'Refrén.'], ['Ž', 'Sloka jedna.']]);
    jeRovno('žalm s číslem jde na Korejs', radkyZalmu(['refren' => 'R', 'sloky' => []], 'Žl 80 (79), 9+12'), [['pokyn', 'Žalm. Zpívá Honza nebo varhanice Lenka. Lid sedí. (Žl 80 (79)) – text a nápěv v Korejsovi.']]);
    echo "Hotovo: $ok v pořádku, $chyb chyb.\n";
    return $chyb ? 1 : 0;
}

if (PHP_SAPI === 'cli' && in_array('--test', $GLOBALS['argv'] ?? [], true)) {
    exit(spustTesty());
}
if (!defined('BS_KNIHOVNA')) { bsHlavni($CFG); } // BS_KNIHOVNA: soubor jde načíst jako knihovna (bez výstupu)
