# Generátor Bohoslužby slova (PHP)

Najde příští neděle a slavnosti, stáhne k nim vlastní texty z liturgie.cz a složí z nich stránku A4 (stejná osnova jako v dokumentu: úvodní obřady, bohoslužba slova, přímluvy, varianta A/B, závěr).

## Spuštění
- Web: nahrajte soubor `bohosluzba.php` na PHP hosting (PHP 8.1+, dom, curl nebo allow_url_fopen; složka `cache/` musí být zapisovatelná). Nastavení a přepínače jsou na začátku souboru a také jako formulář nad výstupem.
  - `bohosluzba.php?od=2026-10-05&pocet=4` (výchozí: od zítřka, 4 příležitosti)
  - `bohosluzba.php?den=2026-11-01` (jeden konkrétní den)
- Příkazová řádka: `php bohosluzba.php --od=2026-10-05 --pocet=4 --vystup=bohosluzby.html`
- Test (volitelný): `php test.php` (kalendář a rozbor stránek na ukázkových datech v `test/`); ostatní soubory ke generování nejsou potřeba.

## Co dělá sám
- Velikonoce, advent, rok A/B/C, číslo neděle v mezidobí, slavnosti (nastavení v bloku NASTAVENÍ na začátku `bohosluzba.php`).
- Gloria jen o nedělích mimo advent a půst a o slavnostech/svátcích; krédo vždy kromě vzpomínky na zemřelé.
- V postní době vynechá Aleluja a zpívá jen verš.
- Druhé čtení vynechá, když ho den nemá.
- U každého dne uvede zdroj (adresu stránky na liturgie.cz) a na konci výstupu seznam odkazů ke kontrole textů (u Květné neděle i pašije).

## Kontrola a vlastní texty (web)
- U každé bohoslužby v seznamu je odkaz „kontrola textů“ (`bohosluzba.php?uprava=RRRR-MM-DD`).
- Stránka ukáže automatický text každé části a odkaz na zdroj. Co sedí, zaškrtněte. Co nesedí, napište do políčka „Vlastní text“. Prázdné políčko = platí automatický text. Žalm: první odstavec je refrén, další odstavce jsou sloky. Verš zadávejte bez Aleluja.
- Tlačítko „Vytvořit bohoslužbu s těmito texty“ pošle texty formulářem a vykreslí bohoslužbu. Na server se nic neukládá, do `cache/` jdou jen stažené stránky z liturgie.cz. Při novém otevření je kontrola prázdná.
- Každá bohoslužba má vlastní krabičku: odkaz na kontrolu, zdroj, žalm s odkazem na Korejs a tlačítko „Tisk / PDF“. Tisk se zpřístupní až po zaškrtnutí všech částí této bohoslužby. Krabička se při tisku nezobrazí.
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
