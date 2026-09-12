#!/usr/bin/env python3
"""Guard count($foo->bar) calls in v2-quote-sheet blade so null relations
don't crash the PDF job. Turns:
    @if(count($coverages->extension_with_type_extention) > 0)
into:
    @if(count($coverages->extension_with_type_extention ?? []) > 0)

Run repeatedly — idempotent (the ?? [] guard is detected and skipped).
"""
import re
import sys
from pathlib import Path

TARGETS = [
    Path("backend/resources/views/v2/livewire/pdf/v2-quote-sheet.blade.php"),
    Path("backend/resources/views/v2/livewire/pdf/v2-quote-sheet-doc.blade.php"),
    Path("backend/resources/views/v2/livewire/pdf/v2-quote-sheet-engineering.blade.php"),
]

# Match count( $foo ) or count( $foo->bar ) or count( $foo->bar->baz )
# — any variable chain. Won't touch if already has ?? [] because the
# whole expression is replaced atomically.
PATTERN = re.compile(
    r"count\(\s*(\$[a-zA-Z_][a-zA-Z0-9_]*(?:->[a-zA-Z_][a-zA-Z0-9_]*)*)\s*\)"
)

def patch(text: str) -> tuple[str, int]:
    count = 0
    def repl(m):
        nonlocal count
        expr = m.group(1)
        count += 1
        return f"count({expr} ?? [])"
    new = PATTERN.sub(repl, text)
    return new, count

total_guards = 0
for path in TARGETS:
    if not path.exists():
        print(f"skip (missing): {path}")
        continue
    orig = path.read_text(encoding="utf-8")
    new, n = patch(orig)
    if n == 0:
        print(f"no-op: {path}")
        continue
    path.write_text(new, encoding="utf-8")
    total_guards += n
    print(f"guarded {n} count() calls in {path}")

print(f"TOTAL: {total_guards} guards applied")
