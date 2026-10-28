# Jupiter – doplnky WooCommerce pre slovenské e-shopy

**Jupiter** je rodina otvorených (open-source) doplnkov pre **WordPress / WooCommerce** pre malé slovenské e-shopy: faktúry, sklad a jednoduché účtovníctvo. Všetky doplnky začínajú písmenom **J** a v administrácii sú hneď pod položkou WooCommerce **Marketing**.

## Doplnky

| Doplnok | Stav | Na čo slúži |
|---|---|---|
| **J-Help** | vo vývoji | Dokumentácia doplnkov Jupiter priamo v administrácii – prehľad, návody, vývoj. |
| **J-Invoices** | plánovaný | Vydané faktúry z objednávok (číselný rad, PDF, QR PAY by square, e-mail zákazníkovi), ručné faktúry, dobropisy, prijaté faktúry s prílohami a splatnosťou, export pre účtovníka. |
| J-Sklad | nápad | Príjemky, výdajky, inventúra. |

## Požiadavky

- WordPress 6.5 alebo novší
- WooCommerce 9 alebo novší
- PHP 8.1 alebo novší

## Inštalácia

Stiahni ZIP doplnku z [Releases](../../releases) a nahraj ho vo WordPresse: **Pluginy → Pridať plugin → Nahrať plugin**.

## Vývoj

- Každý doplnok má vlastný priečinok v `plugins/`.
- Pri každom commite sa automaticky kontroluje syntax PHP.
- Nová verzia sa vydáva značkou v tvare `<doplnok>-v<verzia>`, napr. `j-invoices-v1.0.0` – ZIP doplnku sa potom sám pripojí k vydaniu (Release).

## Licencia

GPL-2.0-or-later – pozri súbor [LICENSE](LICENSE). Copyright © 2026 Aveyona, s. r. o.
