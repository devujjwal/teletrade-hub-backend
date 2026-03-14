#!/usr/bin/env python3
import json
import os
import re
import ssl
import sys
import urllib.parse
import urllib.request
from collections import defaultdict

SUPABASE_URL = os.environ.get('SUPABASE_URL', '').rstrip('/')
SUPABASE_KEY = os.environ.get('SUPABASE_SECRET', '')
INPUT_SQL = os.environ.get('INPUT_SQL', '/tmp/vsmjr110_pg_inserts.sql')
BATCH_SIZE = int(os.environ.get('BATCH_SIZE', '200'))
SKIP_SSL_VERIFY = os.environ.get('SKIP_SSL_VERIFY', '0') == '1'
TABLES_FILTER = [t.strip() for t in os.environ.get('TABLES', '').split(',') if t.strip()]

if not SUPABASE_URL or not SUPABASE_KEY:
    print('Set SUPABASE_URL and SUPABASE_SECRET')
    sys.exit(1)

if not os.path.exists(INPUT_SQL):
    print(f'Input SQL not found: {INPUT_SQL}')
    sys.exit(1)

# Dependency-safe order
TABLE_ORDER = [
    'admin_users',
    'users',
    'categories',
    'brands',
    'warranties',
    'products',
    'product_images',
    'pricing_rules',
    'settings',
    'languages',
    'addresses',
    'orders',
    'order_items',
    'reservations',
    'user_sessions',
    'admin_sessions',
    'vendor_sync_log',
    'vendor_api_logs',
]

INSERT_RE = re.compile(r'^INSERT INTO\s+"([^"]+)"\s*\(([^)]*)\)\s*VALUES\s*(.*);$', re.S)


def statement_iter(path):
    with open(path, 'r', encoding='utf-8', errors='replace') as f:
        buf = []
        in_insert = False
        for line in f:
            if line.startswith('INSERT INTO '):
                in_insert = True
                buf = [line]
                if line.rstrip().endswith(';'):
                    yield ''.join(buf).strip()
                    in_insert = False
                    buf = []
                continue
            if in_insert:
                buf.append(line)
                if line.rstrip().endswith(';'):
                    yield ''.join(buf).strip()
                    in_insert = False
                    buf = []


def split_top_level_commas(s):
    items = []
    start = 0
    depth = 0
    in_str = False
    i = 0
    while i < len(s):
        ch = s[i]
        if ch == "'":
            if in_str:
                if i + 1 < len(s) and s[i + 1] == "'":
                    i += 2
                    continue
                in_str = False
            else:
                in_str = True
        elif not in_str:
            if ch == '(':
                depth += 1
            elif ch == ')':
                depth -= 1
            elif ch == ',' and depth == 0:
                items.append(s[start:i].strip())
                start = i + 1
        i += 1
    tail = s[start:].strip()
    if tail:
        items.append(tail)
    return items


def parse_literal(tok):
    tok = tok.strip()
    if tok.upper() == 'NULL':
        return None
    if tok.startswith("'") and tok.endswith("'"):
        inner = tok[1:-1].replace("''", "'")
        return inner
    if re.fullmatch(r'-?\d+', tok):
        try:
            return int(tok)
        except Exception:
            return tok
    if re.fullmatch(r'-?\d+\.\d+', tok):
        try:
            return float(tok)
        except Exception:
            return tok
    return tok


def parse_rows(values_blob):
    rows = split_top_level_commas(values_blob)
    parsed = []
    for row in rows:
        row = row.strip()
        if row.startswith('(') and row.endswith(')'):
            row = row[1:-1]
        vals = split_top_level_commas(row)
        parsed.append([parse_literal(v) for v in vals])
    return parsed


def post_rows(table, rows, has_id=True):
    if not rows:
        return
    qp = {}
    if has_id:
        qp['on_conflict'] = 'id'
    url = f"{SUPABASE_URL}/rest/v1/{table}"
    if qp:
        url += '?' + urllib.parse.urlencode(qp)

    payload = json.dumps(rows).encode('utf-8')
    req = urllib.request.Request(url, data=payload, method='POST')
    req.add_header('apikey', SUPABASE_KEY)
    req.add_header('Authorization', f'Bearer {SUPABASE_KEY}')
    req.add_header('Content-Type', 'application/json')
    req.add_header('Prefer', 'resolution=merge-duplicates,return=minimal')

    context = ssl._create_unverified_context() if SKIP_SSL_VERIFY else None
    with urllib.request.urlopen(req, timeout=120, context=context) as resp:
        if resp.status not in (200, 201, 204):
            raise RuntimeError(f'Unexpected status {resp.status} for {table}')


def table_has_id(table):
    return True


def main():
    grouped = defaultdict(list)

    for stmt in statement_iter(INPUT_SQL):
        m = INSERT_RE.match(stmt)
        if not m:
            continue
        table = m.group(1)
        if table == 'product_list_view':
            continue
        cols = [c.strip().strip('"') for c in m.group(2).split(',')]
        values_blob = m.group(3).strip()
        grouped[table].append((cols, values_blob))

    order = TABLE_ORDER
    if TABLES_FILTER:
        wanted = set(TABLES_FILTER)
        order = [t for t in TABLE_ORDER if t in wanted]

    for table in order:
        stmts = grouped.get(table, [])
        if not stmts:
            continue

        print(f'Importing {table} statements={len(stmts)}', flush=True)
        inserted = 0
        for cols, values_blob in stmts:
            rows = parse_rows(values_blob)
            batch = []
            for vals in rows:
                rec = {cols[i]: vals[i] if i < len(vals) else None for i in range(len(cols))}
                batch.append(rec)
                if len(batch) >= BATCH_SIZE:
                    post_rows(table, batch, has_id=table_has_id(table))
                    inserted += len(batch)
                    print(f'  {table}: inserted {inserted}', flush=True)
                    batch = []
            if batch:
                post_rows(table, batch, has_id=table_has_id(table))
                inserted += len(batch)
                print(f'  {table}: inserted {inserted}', flush=True)

    print('Import complete', flush=True)


if __name__ == '__main__':
    main()
