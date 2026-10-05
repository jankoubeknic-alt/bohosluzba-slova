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
- Svátky a slavnosti ze sanktorálu (včetně 26.–28. prosince) se nabízejí ve všední den; seznam je v `svatky_ve_vsedni_den` v NASTAVENÍ. Neděli přebíjejí jen pevné slavnosti.
- Památky svatých: povinné (`P`) se slaví vždy, a chybějící části se doplní z feriálního dne, kromě adventu od 17. 12., půstu a oktávů. Nepovinné (`NP`) se slaví jen s nastavením `'vzpominky' => 'vsechny'`. Pokud památka nemá vlastní stránku, použijí se feriální čtení a objeví se hláška „Pozor“.
- Velikonoční vigilie (Velká sobota večer): sedm čtení s žalmy a modlitbami, epištola, evangelium podle roku A/B/C. Každý žalm má odkaz na Korejs.
- Velikonoce a doba kolem: vigilie, Květná neděle s pašijemi (evangelium podle roku A/B/C), Zelený čtvrtek (Glorie, bez Vyznání) a Velký pátek (pašije podle Jana, bez Glorie a Vyznání), Svatý týden po pondělí až středu, oktáv, týdny velikonoční a týden po Letnicích (týdny 8 a 9 mezidobí).
- Všední dny v adventu (do 16. 12. podle týdne, 17.–24. 12. podle data), o vánocích (29.–31. 12., 2.–5. 1., 7.–12. 1.), v postě (Popeleční středa, týdny, Svatý týden po pondělí až středu) a o velikonocích (oktáv, týdny). Mají jedno čtení bez cyklu. Volitelné varianty (např. „1. čtení v cyklu A“) se nebere, použije se hlavní čtení.
- Vstupní modlitba o vánocích má dvě znění podle data: před slavností Zjevení Páně (do 6. 1.), po ní (od 7. 1.).
- Druhé čtení vynechá, když ho den nemá (všední dny ho nemají).
- U bohoslužby uvede zdroj (adresu stránky na liturgie.cz) v krabičce nad textem; u Květné neděle i odkaz na pašije.

## Zatím chybí
- Velikonoční vigilie: obřad světla a křestní část nejsou v bohoslužbě slova; úvod vigilie doplňte z misálu.
- Památky a vzpomínky, které na liturgie.cz odkazují na společné texty nebo mají více formulářů (např. 2. listopadu, sv. Marta, sv. Pio): chybějící části se doplní z feriálního dne téhož data a v bohoslužbě je to uvedeno. Vlastní texty zadáte v kontrole.
- Některé žalmy jsou v misálu jen jako alternativa a parser je neobere; doplní se z feriálního dne nebo zadají ručně.

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
