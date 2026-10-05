# Generátor Bohoslužby slova (PHP)

Najde příští neděle a slavnosti, stáhne k nim vlastní texty z liturgie.cz a složí z nich stránku A4 (stejná osnova jako v dokumentu: úvodní obřady, bohoslužba slova, přímluvy, varianta A/B, závěr).

## Spuštění
- Web: nahrajte soubor `bohosluzba.php` na PHP hosting (PHP 8.1+, dom, curl nebo allow_url_fopen; složka `cache/` musí být zapisovatelná). Den vyberete v kalendáři ve formuláři nahoře; nastavení jsou na začátku souboru a také ve formuláři.
  - `bohosluzba.php` (výchozí: nejbližší den s bohoslužbou od zítřka)
  - `bohosluzba.php?den=2026-10-18` (konkrétní den)
- Příkazová řádka: `php bohosluzba.php --den=2026-10-18 --vystup=bohosluzba.html`
- Test (volitelný): `php test.php` (kalendář a rozbor stránek na ukázkových datech v `test/`); ostatní soubory ke generování nejsou potřeba.

## Co dělá sám
- Velikonoce, advent, rok A/B/C, číslo neděle v mezidobí, slavnosti (nastavení v bloku NASTAVENÍ na začátku `bohosluzba.php`).
- Gloria a vyznání víry jen o nedělích a slavnostech/svátcích (Gloria ne v adventu a postě); všední dny je nemají.
- V postní době vynechá Aleluja a zpívá jen verš.
- Všední dny v mezidobí: text ze stránky týdne (`NN_0D.htm`). Cyklus čtení je I v lichých letech a II v sudých (kalendářní rok, podle indexu liturgie.cz). Stránka ukáže „feriální cyklus I/II“. Verš a evangelium jsou společné pro oba cykly.
- Svátky (2. 2., 6. 8., 14. 9., 9. 11.) se nabízejí i ve všední den; seznam je v `svatky_ve_vsedni_den` v NASTAVENÍ.
- Všední dny v adventu (do 16. 12. podle týdne, 17.–24. 12. podle data), o vánocích (29.–31. 12., 2.–5. 1., 7.–12. 1.), v postě (Popeleční středa, týdny, Svatý týden po pondělí až středu) a o velikonocích (oktáv, týdny). Mají jedno čtení bez cyklu. Volitelné varianty (např. „1. čtení v cyklu A“) se nebere, použije se hlavní čtení.
- Vstupní modlitba o vánocích má dvě znění podle data: před slavností Zjevení Páně (do 6. 1.), po ní (od 7. 1.).
- Druhé čtení vynechá, když ho den nemá (všední dny ho nemají).
- U bohoslužby uvede zdroj (adresu stránky na liturgie.cz) v krabičce nad textem; u Květné neděle i odkaz na pašije.

## Zatím chybí
- Velký čtvrtek až sobota (triduum) a Velikonoční vigilie: stránky nejsou napojené. Den se nezobrazí a objeví se hláška s formulářem, takže jde zvolit jiný den.
- Všední dny od pondělí po Seslání Ducha svatého do soboty před Nejsvětější Trojicí a 26.–28. prosince (svatí o Vánocích).
- Památky svatých: všední den se bere jako feriální, památka se nezohledňuje.
- Pašije na Květnou neděli: evangelium se ručně doplní.
- Některé dny mají formuláře nebo více variant, které parser nerozliší (např. 2. listopadu, vzpomínka na zemřelé). Chybějící část se označí jako „Pozor“.

## Kontrola a vlastní texty (web)
- Nad textem je krabička s odkazem „Zkontrolovat texty“ (`bohosluzba.php?uprava=RRRR-MM-DD`).
- Stránka ukáže automatický text každé části a odkaz na zdroj. Co sedí, zaškrtněte. Co nesedí, napište do políčka „Vlastní text“. Prázdné políčko = platí automatický text. Žalm: první odstavec je refrén, další odstavce jsou sloky. Verš zadávejte bez Aleluja.
- Tlačítko „Vytvořit bohoslužbu s těmito texty“ pošle texty formulářem a vykreslí bohoslužbu. Na server se nic neukládá, do `cache/` jdou jen stažené stránky z liturgie.cz. Při novém otevření je kontrola prázdná.
- Krabička obsahuje odkaz na kontrolu, zdroj, žalm s odkazem na Korejs a tlačítko „Tisk / PDF“. Tisk se zpřístupní až po odeslání kontroly se všemi zaškrtnutými částmi. Krabička se při tisku nezobrazí.
- Tisk nebo PDF: Ctrl+P v prohlížeči.
- Stránka nemá přihlášení. Úpravy se ale nikam neukládají, takže nic nepřepíše. Na veřejném hostingu si ale stránku stejně nemusíte zpřístupňovat.
- Žalm zpívá Honza nebo varhanice Lenka. Ve výstupu je jen číslo žalmu a odkaz na PDF nápěvu.
- Přímluvy (modrá knížka) se do kontroly nezadávají. Zůstávají jako „VLASTNÍ TEXT PŘÍMLUV“.

## Žalm a nápěv
- Číslo žalmu bere generátor z misálu (Mezizpěv, např. „Žl 80“). Text žalmu do bohoslužby nevkládá.
- Nápěv a text zpívá Vlasta nebo varhanice Lenka. Ve výstupu je to jen pokyn s číslem žalmu.
- Tlačítko „Tisk žalmu (Korejs)“ otevře PDF žalmu z https://sdh.cz/korejs/ v nové záložce. Vybere se podle lekcionáře (číslo žalmu, verše, příležitost, cyklus). PDF se nekopírují, generátor jen odkazuje.
- Tabulka se stáhne jednou za cache_dni a uloží do `cache/korejs_podlelekcionare.html`. Nastavení: `'korejs'` v bloku NASTAVENÍ.

## Co zůstává ručně
- Přímluvy (modrá knížka) a homilie (Minutěnky). Pokud se kterákoli část nepodaří najít, v textu zůstane `VLASTNÍ TEXT` a nahoře se objeví rámeček „Pozor“.

## Upozornění
- Texty jsou © ČBK / Liturgie.cz; web je určen k přípravě. Generátor stahuje každou stránku jednou a ukládá do `cache/` (90 dní). Používejte pro vlastní přípravu, nezveřejňujte.
- Rozbor stránek vychází ze struktury misálu na m.liturgie.cz (h3 název dne, h4 části, „Čtení z Písma v roce A/B/C“; u slavností jsou bloky A, B, C za sebou). Ověřeno na nedělích, adventu, vánocích a slavnostech včetně Nanebevstoupení, Seslání, Trojice a Boží Tělo. Přesto při prvním použití porovnejte výstup s liturgie.cz.
- Květná neděle se čte z pašijí (h4 „Pašije v ročním cyklu A/B/C“), které generátor zatím nerozpoznává; označí se rámečkem „Pozor“ a evangelium doplníte ručně.
- Nanebevstoupení a Boží Tělo se podle bloku NASTAVENÍ na začátku `bohosluzba.php` berou ve čtvrtek; přepněte na `'nedele'`, pokud je u vás slavíte v neděli.

## Spuštění v Dockeru (localhost)
```
docker build -t bohosluzba .
docker run --rm -p 127.0.0.1:8080:80 bohosluzba
```
Pak otevřete http://localhost:8080/bohosluzba.php. Cache se ukládá v kontejneru a při jeho zrušení zmizí.

## Licence a autorská práva
- Kód generátoru (`bohosluzba.php`, `test.php`, `Dockerfile`, tento README) je pod licencí MIT, viz `LICENSE`.
- Texty bohoslužeb (modlitby, čtení, žalmy, evangelia, krédo, Gloria a další) jsou © Česká biskupská konference / Liturgie.cz (Český misál a Mešní lekcionář). Repozitář je neobsahuje. Generátor je stahuje z liturgie.cz do `cache/`, které je v `.gitignore`.
- Soubory v `test/` jsou vymyšlená ukázková data pro testy, žádný text z liturgie.cz v nich není.
- Nápěvy a PDF žalmů jsou projekt Korejs (https://sdh.cz/korejs/). Generátor na ně jen odkazuje a nic z nich neukládá.
- Vygenerované bohoslužby slouží k přípravě. Nešiřte je bez svolení nositele práv.
