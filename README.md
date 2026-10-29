# Jupiter – doplnky WooCommerce pre slovenské e-shopy

**Jupiter** je rodina otvorených (open-source) doplnkov pre **WordPress / WooCommerce** pre malé slovenské e-shopy: faktúry, sklad a jednoduché účtovníctvo. Všetky doplnky začínajú písmenom **J** a v administrácii sú hneď pod položkou WooCommerce **Marketing**.

## Doplnky

| Doplnok | Verzia | Na čo slúži |
|---|---|---|
| **J-Pomocník** | 1.1.5 | Dokumentácia doplnkov Jupiter priamo v administrácii – prehľad, návody, plán vývoja – a aktualizácie doplnkov z GitHubu. |
| **J-Fakturácia** | 0.10.2 | Faktúry, zálohové faktúry a dobropisy z objednávok (číselný rad, PDF s QR PAY by square, e-mail zákazníkovi), ručné faktúry, prijaté faktúry s prílohami, splatnosťou a úhradami, adresár dodávateľov, pole „Nakupujem ako firma“, export pre účtovníka a do Pohody, prechod z Woo Faktura. Zatiaľ pre neplatiteľov DPH. |
| **J-Účtovníctvo** | 0.6.0 | Podvojné účtovníctvo pre slovenské s. r. o.: sprievodca založením (firma, obdobie, rozvrh, počiatočné stavy), účtový rozvrh podľa rámcovej osnovy, účtovné doklady so stornom, denník, hlavná kniha, obratová predvaha, saldokonto, automatické účtovanie faktúr, banky a skladu. Účtovná závierka mikro účtovnej jednotky (Úč MÚJ). Pokladňa, majetok a odpisy, uzávierka mesiaca a roka (daň z príjmov, 710/702/701, poznámky Úč MÚJ), Na riešenie (úlohy a kontroly), import z OBERON-u. |
| **J-Notes** | 0.1.0 | Poznámky k projektom: plány s úlohami a stavom (☐ ◐ ☑), dokumentácia v Markdowne, prehľad „Teraz robím“, denník zmien, súbory .md (nahratie aj cez FTP). Rovnaký kód používa My Notes. |
| **J-Pošta** (j-emails) | 0.5.0 | **Jupiter Pošta** – pošta obchodu v administrácii v štýle Gmailu: konverzácie, hviezdičky, Dôležité, Archív, Odložené, rýchle a hromadné akcie, okno Napísať, aliasy odosielateľa; e-maily webu a e-shopu cez SMTP; šablóny e-mailov; faktúry z externého účtu Google (Gmail API) priamo do J-Fakturácie. |
| **J-Objednávky** | 0.2.0 | Prehľadný zoznam objednávok nad WooCommerce (stavy s počtami, vyhľadávanie aj podľa čísla, filtre, hromadná zmena stavu) a vlastné čísla objednávok s prefixom – náhrada doplnku My Order Numbers. |
| **J-Sklad** | 0.4.0 | Sklad nad produktmi WooCommerce: príjemky a výdajky, inventúra, kniha pohybov zásob, vážená priemerná nákupná cena a hodnota skladu, evidencia obrázkov produktov, akcie a novinky (zľavy s dátumom, štítky v obchode). Prepojený s dodávateľmi a prijatými faktúrami J-Fakturácie. Pripravuje sa PDF, návrh objednávky tovaru. |

Všetky doplnky sú zatiaľ vo vývoji (verzie 0.x okrem J-Pomocníka).

## Požiadavky

- WordPress 6.5 alebo novší
- WooCommerce 9 alebo novší
- PHP 8.1 alebo novší

## Inštalácia a aktualizácie

1. Stiahni ZIP doplnku z [Releases](../../releases) a nahraj ho vo WordPresse: **Pluginy → Pridať plugin → Nahrať plugin**. Vždy nainštaluj aj **J-Pomocník**.
2. Rovnaký kód môže bežať na ľubovoľnom počte e-shopov – **každý web má vlastné údaje a nastavenia**, weby si nič nevymieňajú.
3. Aktualizácie: J-Pomocník kontroluje súbor [`updates.json`](updates.json). Keď vyjde nová verzia, vo WordPresse sa v Pluginoch ukáže **Dostupná aktualizácia** (dá sa zapnúť aj automatická aktualizácia).

## Vývoj

- Každý doplnok má vlastný priečinok v `plugins/`.
- Pri každom commite sa automaticky kontroluje syntax PHP.
- Nová verzia: zvýš `Version` (hlavička) aj konštantu `*_VERSION` v doplnku a uprav `updates.json` (verzia, `package`, poznámky). Po zlúčení do vetvy `main` sa **značka `<doplnok>-v<verzia>`, vydanie (Release) aj ZIP vytvoria automaticky** a weby ponúknu aktualizáciu.
- Kontrola pri každom commite overí, že hlavička, konštanta a `updates.json` majú rovnakú verziu (`.github/scripts/check-versions.php`). J-Pomocník prijme aktualizáciu len z vydaní tohto repozitára.

## Licencia

GPL-2.0-or-later – pozri súbor [LICENSE](LICENSE). Copyright © 2026 Aveyona, s. r. o.
