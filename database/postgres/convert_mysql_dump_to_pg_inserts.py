#!/usr/bin/env python3
import re
import sys
from pathlib import Path

if len(sys.argv) < 3:
    print(f"Usage: {sys.argv[0]} <mysql_dump.sql> <output_inserts.sql>")
    sys.exit(1)

src = Path(sys.argv[1])
out = Path(sys.argv[2])

if not src.exists():
    print(f"MySQL dump not found: {src}")
    sys.exit(1)

lines = src.read_text(encoding="utf-8", errors="replace").splitlines()

in_insert = False
buffer = []
converted = []

for line in lines:
    if line.startswith("INSERT INTO "):
        in_insert = True
        buffer = [line]
        if line.rstrip().endswith(";"):
            in_insert = False
            stmt = "\n".join(buffer)
            buffer = []
            if "INSERT INTO `product_list_view`" in stmt:
                continue
            converted.append(stmt)
        continue

    if in_insert:
        buffer.append(line)
        if line.rstrip().endswith(";"):
            in_insert = False
            stmt = "\n".join(buffer)
            buffer = []
            if "INSERT INTO `product_list_view`" in stmt:
                continue
            converted.append(stmt)

output_sql = "\n\n".join(converted) + "\n"

# MySQL -> PostgreSQL normalization for INSERT statements only
output_sql = output_sql.replace("`", '"')
output_sql = output_sql.replace("\\x00", "")
output_sql = output_sql.replace("\\'", "''")
output_sql = re.sub(r"'0000-00-00 00:00:00'", "NULL", output_sql)
output_sql = re.sub(r"'0000-00-00'", "NULL", output_sql)

out.write_text(output_sql, encoding="utf-8")
print(f"PostgreSQL INSERT file generated: {out}")
