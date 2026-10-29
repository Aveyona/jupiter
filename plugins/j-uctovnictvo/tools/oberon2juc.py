#!/usr/bin/env python3
"""
Prevod databázy OBERON (Exalogic) do importného súboru J-Účtovníctva (formát juc-import/1).

Použitie:
    python3 oberon2juc.py Firma.mdb [Firma_OLD.mdb ...] -o import.json

Potrebuje mdbtools (príkaz mdb-export). Číta tabuľky UctovnyDennik (účtovný denník),
FirmaPartner (adresár) a FirmaUdaje (údaje firmy). Do výstupu nejde nič iné.

Úpravy pri prevode:
  - účty: syntetický „518000“ → „518“, analytika „518001“ → „518901“ s názvom z OBERON-u
    (aby nekolidovala s vlastnými analytikami), pokladňa a banka → 211001 a 221001, „22001“ → „022901“;
  - jednostranné riadky dokladu (napr. faktúra: Dal 321 celkom, MD 518/504 po položkách) sa spárujú
    na zápisy MD/Dal; doklad, ktorý sa nedá vyrovnať, sa vypíše ako chyba;
  - rad dokladu podľa typu: PS počiatočný stav, UZ účtovná závierka, BV banka, PD/VD pokladňa,
    DF záväzok, OF pohľadávka, MZ mzdy, ID interný doklad.
"""
import argparse
import csv
import io
import json
import subprocess
import sys
from collections import OrderedDict, defaultdict
from datetime import datetime

SERIES = [
    ('počiat', 'PS'), ('závierka', 'UZ'), ('bu', 'BV'), ('pokl.-príjem', 'PD'), ('pokl.-výdaj', 'VD'),
    ('záväzok', 'DF'), ('pohľadávka', 'OF'), ('mzdy', 'MZ'), ('int', 'ID'),
]


def table(mdb, name):
    try:
        out = subprocess.run(['mdb-export', '-D', '%Y-%m-%d', '-T', '%Y-%m-%d', mdb, name],
                             check=True, capture_output=True).stdout.decode('utf-8', 'replace')
    except subprocess.CalledProcessError:
        return []
    return list(csv.DictReader(io.StringIO(out)))


def raw_acc(v):
    v = (v or '').strip().split('.')[0]
    return v.zfill(6) if v and len(v) <= 6 else v


# Účty, ktoré majú v J-Účtovníctve rovnaký význam ako v OBERON-e (pokladňa, Fio).
SAME = {'211001': '211001', '221001': '221001', '211000': '211001', '221000': '221001'}


class AccMap:
    """Prevod účtov OBERON → J-Účtovníctvo. Syntetický „xxx000“ → „xxx“; analytika „xxx0NN“ → „xxx9NN“
    (nekoliduje s vlastnými analytikami J-Účtovníctva). Ak má rovnaké číslo v rôznych rokoch iný názov
    (napr. 518001 raz telefón, inokedy nedaňové služby), neskorší význam dostane „xxx8NN“."""

    def __init__(self):
        self.names = {}      # (rok, oberon účet) -> (názov, nedaňový)
        self.out = OrderedDict()   # nový účet -> {num, name, tax, oberon}
        self.variant = {}    # (oberon účet, názov) -> nový účet

    def load(self, mdb):
        for x in table(mdb, 'Osnova'):
            n = raw_acc(x['Cislo'])
            y = int(float(x['UctovneObdobie'] or 0))
            self.names[(y, n)] = ((x['Nazov'] or '').strip(), (x.get('ZdanovanaCinnost') or '0') in ('1', '1.0'))

    def __call__(self, v, year):
        n = raw_acc(v)
        if not n:
            return ''
        if n in SAME:
            return SAME[n]
        if n.endswith('000') or len(n) != 6:
            return n[:3]
        name, nontax = self.names.get((year, n), ('', False))
        key = (n, name.lower())
        if key not in self.variant:
            used = {k[0] for k in self.variant}
            new = n[:3] + ('8' if n in used else '9') + n[4:]
            self.variant[key] = new
            self.out[new] = {'num': new, 'name': (name or 'Účet ' + n) + ' (OBERON ' + n + ')', 'tax': 0 if nontax else 1, 'oberon': n}
        return self.variant[key]


acc = AccMap()


def num(v):
    try:
        return round(float(v or 0), 2)
    except ValueError:
        return 0.0


def ymd(v):
    v = (v or '').strip()[:10]
    for f in ('%Y-%m-%d', '%m/%d/%y'):
        try:
            return datetime.strptime(v, f).strftime('%Y-%m-%d')
        except ValueError:
            pass
    return ''


def series(typ):
    t = (typ or '').lower()
    for k, s in SERIES:
        if k in t:
            return s
    return 'ID'


def pair(rows, year):
    """Riadky dokladu → zápisy MD/Dal. Vráti (lines, chyba)."""
    lines, md1, d1 = [], [], []
    for r in rows:
        m, d = acc(r['UcetMaDAT'], year), acc(r['UcetDAL'], year)
        text = (r.get('TextDopln') or r.get('Text') or '').strip()
        if m and d:
            lines.append({'md': m, 'd': d, 'amount': num(r['SumaMaDAT']), 'text': text})
        elif m:
            md1.append([m, num(r['SumaMaDAT']), text])
        elif d:
            d1.append([d, num(r['SumaDAL']), text])
    if round(sum(x[1] for x in md1) - sum(x[1] for x in d1), 2):
        return lines, 'jednostranné riadky nie sú vyrovnané'
    # Postupné párovanie (pri jednom protiúčte = rozpis položiek).
    i = j = 0
    while i < len(md1) and j < len(d1):
        a = round(min(md1[i][1], d1[j][1]), 2)
        if a:
            lines.append({'md': md1[i][0], 'd': d1[j][0], 'amount': a, 'text': md1[i][2] or d1[j][2]})
        md1[i][1] = round(md1[i][1] - a, 2)
        d1[j][1] = round(d1[j][1] - a, 2)
        if not md1[i][1]:
            i += 1
        if not d1[j][1]:
            j += 1
    return lines, ''


def convert(mdb, label):
    acc.load(mdb)
    rows = table(mdb, 'UctovnyDennik')
    groups = OrderedDict()
    for r in rows:
        key = (r['Obdobie'].split('.')[0], r['IDNum'].split('.')[0])
        groups.setdefault(key, []).append(r)
    entries, errors = [], []
    for (year, idn), g in groups.items():
        g.sort(key=lambda r: int(float(r['Row'] or 0)))
        h = g[0]
        lines, err = pair(g, int(year))
        if err or not lines:
            errors.append(f'{label} {year}/{idn}: {err or "bez zápisov"}')
            continue
        ser = series(h['DokladTyp'])
        number = (h['Doklad'] or '').strip() or (h['ExtDoklad'] or '').strip() or f'{ser}{year}-{idn}'
        vs = (h.get('ZavazkyVariabilnySymbol') or h.get('PohladavkyVariabilnySymbol') or '').strip()
        date = ymd(h['Datum'])
        entries.append({
            'ext_id': f'{year}-{idn}',
            'year': int(year),
            'date': date,
            'series': ser,
            'number': number,
            'ext_doc': (h['ExtDoklad'] or '').strip(),
            'descr': (h['Text'] or '').strip(),
            'partner': (h['ObchPartner'] or '').strip(),
            'vs': vs,
            'opening': ser == 'PS',
            'lines': lines,
        })
    partners = []
    for p in table(mdb, 'FirmaPartner'):
        partners.append({k: (p.get(s) or '').strip() for k, s in (
            ('name', 'Nazov'), ('ico', 'ICO'), ('dic', 'DIC'), ('icdph', 'ICDPH'), ('street', 'Ulica'),
            ('city', 'Obec'), ('zip', 'PSC'), ('iban', 'IBAN1'), ('email', 'Email'), ('phone', 'Tel'))})
    return entries, partners, errors


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('mdb', nargs='+')
    ap.add_argument('-o', '--out', default='-')
    a = ap.parse_args()
    by_year, partners, errors = {}, OrderedDict(), []
    for mdb in a.mdb:
        e, p, err = convert(mdb, mdb)
        errors += err
        years = defaultdict(list)
        for x in e:
            years[x['year']].append(x)
        for y, lst in years.items():
            if y in by_year:
                print(f'Rok {y} je vo viacerých databázach – použije sa {mdb}.', file=sys.stderr)
            by_year[y] = lst
        for x in p:
            if x['name']:
                partners[(x['ico'] or x['name']).lower()] = x
    entries = [x for y in sorted(by_year) for x in sorted(by_year[y], key=lambda x: (x['date'], x['series'] != 'PS', x['ext_id']))]
    out = {'format': 'juc-import/1', 'source': 'OBERON', 'created': datetime.now().isoformat(timespec='seconds'),
           'years': sorted(by_year), 'accounts': list(acc.out.values()), 'partners': list(partners.values()), 'entries': entries, 'errors': errors}
    data = json.dumps(out, ensure_ascii=False, indent=1)
    if a.out == '-':
        print(data)
    else:
        open(a.out, 'w', encoding='utf-8').write(data)
    for e in errors:
        print('CHYBA:', e, file=sys.stderr)
    print(f'Roky {out["years"]}, dokladov {len(entries)}, partnerov {len(partners)}, chýb {len(errors)}', file=sys.stderr)


if __name__ == '__main__':
    main()
