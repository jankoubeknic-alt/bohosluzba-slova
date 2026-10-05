# Generátor Bohoslužby slova (PHP)

Vybere den (neděle, slavnost, svátek, všední den, památka), stáhne k němu texty z liturgie.cz a složí z nich stránku A4 se stejnou osnovou jako v dokumentu: úvodní obřady, bohoslužba slova, přímluvy, varianta A/B, závěrečné obřady.

Celý generátor je **jediný soubor `bohosluzba.php`** včetně vestavěných testů a syntetických ukázek. Nic dalšího ke spuštění není potřeba.

## Spuštění
- **Web:** nahrajte `bohosluzba.php` na PHP hosting (PHP 8.1+ s rozšířeními dom a curl nebo allow_url_fopen; složka `cache/` musí být zapisovatelná). Den vyberete v kalendáři ve formuláři nahoře; přepínače jsou v NASTAVENÍ na začátku souboru a také ve formuláři.
  - `bohosluzba.php` – výchozí den: nejbližší den s bohoslužbou od zítřka
  - `bohosluzba.php?den=2026-10-18` – konkrétní den
  - `bohosluzba.php?den=2026-11-02&zformular=2` – vzpomínka na zemřelé s 2. formulářem
- **Příkazová řádka:** `php bohosluzba.php --den=2026-10-18 --vystup=bohosluzba.html` (přepínač `--zformular=1|2|3` a ostatní nastavení jako ve formuláři).
- **Testy:** `php bohosluzba.php --test` (kalendář, rozbor stránek, vigilie, památky a výběr formuláře na syntetických ukázkách; žádná síť, dočasné soubory se smažou).

## Co generátor zvládne sám
- **Kalendář:** Velikonoce, advent, rok A/B/C, období, neděle v mezidobí, pevné slavnosti a svátky, přesun slavnosti na pondělí, Nanebevstoupení a Boží Tělo ve čtvrtek nebo v neděli (nastavení).
- **Neděle a slavnosti:** čtení podle roku A/B/C, žalm, 2. čtení, verš a evangelium. Gloria a Vyznání víry podle pravidel (Gloria ne v adventu a postě).
- **Postní doba:** bez Aleluja, zpívá se jen verš. Popeleční středa a týden po ní, Svatý týden po pondělí až středu (Velký čtvrtek a pátek jsou níže).
- **Velikonoční triduum a Květná neděle:** Zelený čtvrtek (Glorie, bez Vyznání), Velký pátek (pašije podle Jana, bez Glorie a Vyznání), Květná neděle s pašijemi podle roku A/B/C.
- **Velikonoční vigilie (Velká sobota večer):** sedm čtení s žalmy a modlitbami, epištola, evangelium podle roku. Každý žalm má odkaz na Korejs.
- **Velikonoční oktáv, týdny velikonoční a týden po Letnicích:** stránky týdnů (týdny 8 a 9 mezidobí).
- **Všední dny v mezidobí:** text ze stránky týdne (`NN_0D.htm`). Cyklus čtení je I v lichých letech a II v sudých (kalendářní rok; ověřeno na indexu liturgie.cz). Verš a evangelium jsou společné pro oba cykly. Volitelné varianty („1. čtení v cyklu A“, „Evangelium v cyklu A“) se nebere, použije se hlavní čtení.
- **Všední dny v adventu:** do 16. 12. podle týdne, 17.–24. 12. podle data.
- **Vánoce:** 29.–31. 12., 2.–5. 1. a 7.–12. 1. podle data. Vstupní modlitba o vánocích má dvě znění podle data (před a po slavnosti Zjevení Páně).
- **Svátky a slavnosti ze sanktorálu** (včetně 26.–28. 12.) se nabízejí ve všední den. Neděli přebíjejí jen pevné slavnosti.
- **Památky svatých:** povinné (`P`) se slaví vždy, kromě adventu od 17. 12., půstu a oktávů. Nepovinné (`NP`) jen s nastavením `'vzpominky' => 'vsechny'`.
- **Vzpomínka na zemřelé (2. 11.):** formulář 1, 2 nebo 3 (nastavení `zesnuli_formular`, přepínač `zformular`) určuje vstupní modlitbu a modlitbu po přijímání.
- **Zdroj a krabička:** u každé bohoslužby zdroj (adresa na liturgie.cz), odkaz na kontrolu, žalm s odkazem na Korejs a tlačítko tisku.

## Co doplní z feriálního dne nebo vyžaduje ruční úpravu
- **Chybějící části památky nebo svátku** (např. sv. Marta, sv. Pio, když stránka odkazuje na společné texty): doplní se z feriálního dne téhož data. Bohoslužba to uvádí jako informaci („Doplněno z feriálního dne“). Vlastní texty zadáte v kontrole.
- **Vzpomínka na zemřelé:** čtení a evangelium z feriálního dne. Formulář určuje jen vstupní a po přijímání; čtení jsou na stránce navázaná na formuláře nejasně, proto se neodhadují.
- **Památka bez vlastní stránky** (u povinných zatím nenastává): feriální čtení a hláška „Pozor“.
- **Velikonoční vigilie:** obřad světla a křestní část nejsou v bohoslužbě slova; úvod doplňte z misálu.
- **Žalm, který je na stránce jen jako alternativa:** parser ho neobere; doplní se z feriálního dne nebo se zadá ručně (hláška „chybí žalm“).
- **Přímluvy** (modrá knížka) a **homilie** (Minutěnky) se do kontroly nezadávají; v bohoslužbě zůstávají jako „VLASTNÍ TEXT“.
- Pokud se jakákoli část nenajde, zůstane v textu `VLASTNÍ TEXT` a nahoře se objeví rámeček „Pozor“.

## Kontrola a vlastní texty (web)
- Nad textem je krabička s odkazem „Zkontrolovat texty“ (`bohosluzba.php?uprava=RRRR-MM-DD`).
- Stránka ukáže automatický text každé části a odkaz na zdroj. Co sedí, zaškrtněte. Co nesedí, napište do políčka „Vlastní text“. Prázdné políčko = platí automatický text. Žalm: první odstavec je refrén, další odstavce jsou sloky. Verš zadávejte bez Aleluja. U vigilie je každé čtení zvlášť.
- Tlačítko „Vytvořit bohoslužbu s těmito texty“ pošle texty formulářem a vykreslí bohoslužbu. **Na server se nic neukládá.** Do `cache/` jdou jen stažené stránky z liturgie.cz. Při novém otevření je kontrola prázdná.
- Krabička obsahuje odkaz na kontrolu, zdroj, žalm (u vigilie všechny) s odkazem na Korejs a tlačítko „Tisk / PDF“. Tisk se zpřístupní až po odeslání kontroly se všemi zaškrtnutými částmi. Krabička se při tisku nezobrazí.
- Tisk nebo PDF: Ctrl+P v prohlížeči.
- Stránka nemá přihlášení. Úpravy se nikam neukládají, takže nic nepřepíše. Na veřejném hostingu si ji ale stejně nemusíte zpřístupňovat.
- Žalm zpívá Honza nebo varhanice Lenka. Ve výstupu je jen číslo žalmu a odkaz na PDF nápěvu.

## Žalm a nápěv
- Číslo žalmu bere generátor z misálu (Mezizpěv, např. „Žl 80“). Text žalmu do bohoslužby nevkládá.
- Tlačítko „Tisk žalmu (Korejs)“ otevře PDF žalmu z https://sdh.cz/korejs/ v nové záložce. Vybere se podle lekcionáře (číslo žalmu, verše, příležitost, cyklus). PDF se nekopírují, generátor jen odkazuje.
- Tabulka lekcionáře se stáhne jednou za `cache_dni` a uloží do `cache/korejs_podlelekcionare.html`. Nastavení: `'korejs'` v NASTAVENÍ.

## Nastavení (blok NASTAVENÍ na začátku `bohosluzba.php`)
- `zdroj` – odkud se texty berou (výchozí liturgie.cz; proměnná prostředí `BS_ZDROJ` nebo lokální kopie).
- `nanebevstoupeni`, `bozi_telo` – `'ctvrtek'` nebo `'nedele'`.
- `slavnosti_ve_vsedni_den` – nabízet slavnosti a svátky ve všední den.
- `svatky_ve_vsedni_den` – seznam měsíc-den slavností a svátků, které se nabízejí ve všední den. Co nechcete, smažte.
- `vzpominky` – `'povinne'` (výchozí) nebo `'vsechny'` (i nepovinné památky).
- `zesnuli_formular` – 1, 2 nebo 3 pro vzpomínku na zemřelé.
- `vcetne_dnes` – hledat od dneška, nebo od zítřka.

## Upozornění
- Texty jsou © ČBK / Liturgie.cz; web je určen k přípravě. Generátor stahuje každou stránku jednou a ukládá ji do `cache/` (90 dní). Používejte pro vlastní přípravu, nezveřejňujte.
- Rozbor stránek vychází ze struktury misálu na m.liturgie.cz. Ověřeno na nedělích, adventu, vánocích, postu, velikonocích, slavnostech a v celém roce 2026–2027 (730 dnů s bohoslužbou). Přesto při prvním použití porovnejte výstup s liturgie.cz.
- Společné texty (na které stránky odkazují) generátor nečte. Pokud se na nich stránka dne odvolává, doplní se z feriálního dne.

## Spuštění v Dockeru (localhost)
```
docker build -t bohosluzba .
docker run --rm -p 127.0.0.1:8080:80 bohosluzba
```
Pak otevřete http://localhost:8080/bohosluzba.php. Image obsahuje jen `bohosluzba.php`. Cache se ukládá v kontejneru a při jeho zrušení zmizí.

## Licence a autorská práva
- Kód generátoru (`bohosluzba.php`, `Dockerfile`, tento README) je pod licencí MIT, viz `LICENSE`.
- Texty bohoslužeb (modlitby, čtení, žalmy, evangelia, krédo, Gloria a další) jsou © Česká biskupská konference / Liturgie.cz (Český misál a Mešní lekcionář). Repozitář je neobsahuje. Generátor je stahuje z liturgie.cz do `cache/`, které je v `.gitignore`.
- Testovací ukázky uvnitř `bohosluzba.php` jsou vymyšlená data. Žádný text z liturgie.cz v nich není.
- Nápěvy a PDF žalmů jsou projekt Korejs (https://sdh.cz/korejs/). Generátor na ně jen odkazuje a nic z nich neukládá.
- Vygenerované bohoslužby slouží k přípravě. Nešiřte je bez svolení nositele práv.
