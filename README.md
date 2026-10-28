# Jupiter – doplnky WooCommerce pre slovenské e-shopy

**Jupiter** je rodina otvorených (open-source) doplnkov pre **WordPress / WooCommerce** pre malé slovenské e-shopy: faktúry, sklad a jednoduché účtovníctvo. Všetky doplnky začínajú písmenom **J** a v administrácii sú hneď pod položkou WooCommerce **Marketing**.

## Doplnky

| Doplnok | Stav | Na čo slúži |
|---|---|---|
| **J-Pomocník** | vo vývoji | Dokumentácia doplnkov Jupiter priamo v administrácii – prehľad, návody, vývoj. |
| **J-Fakturácia** | vo vývoji (0.1.0) | Vydané faktúry z objednávok (číselný rad, PDF, QR PAY by square, e-mail zákazníkovi po potvrdení), ručné faktúry, dobropisy, prijaté faktúry s prílohami a splatnosťou, export pre účtovníka. |
| **J-Emails** | vo vývoji (0.1) | E-mailová schránka obchodu v administrácii (IMAP/SMTP): doručená pošta, odoslané, kôš, odpovede, prílohy. Pripravuje sa Mailchimp pre zákazníkov. |
| J-Sklad | nápad | Príjemky, výdajky, inventúra. |

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
- Nová verzia: zvýš `Version` v doplnku, uprav `updates.json` a vytvor značku `<doplnok>-v<verzia>` (napr. `j-invoices-v1.0.0`) – ZIP doplnku sa sám pripojí k vydaniu (Release) a weby ponúknu aktualizáciu.

## Licencia

GPL-2.0-or-later – pozri súbor [LICENSE](LICENSE). Copyright © 2026 Aveyona, s. r. o.
