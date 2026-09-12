"""
One-shot fix: wrap `foreach($x->prop as ...)` and `@foreach($x->prop as ...)`
with a null-coalesce `($x->prop ?? [])` in a specific Blade so that
nullable Eloquent relations (e.g. a coverage with no specified_items)
stop throwing `foreach() argument must be of type array|object, null given`.

The v2-quote-sheet blade has ~60 foreach loops over nullable relations;
touching each one by hand is both error-prone and far worse for diff
review than a deterministic regex pass.
"""
import re, sys, pathlib

target = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else pathlib.Path(
    'backend/resources/views/v2/livewire/pdf/v2-quote-sheet.blade.php')

src = target.read_text(encoding='utf-8')

# Match `foreach($var->prop as ...)` and `@foreach($var->prop as ...)` and
# `@forelse($var->prop as ...)`. Allow chained arrow access
# (`$obj->rel->subrel`) and keep whatever comes after ` as ` intact.
pat_raw   = re.compile(r'(\bforeach\s*\()(\$[A-Za-z_]\w*(?:->[A-Za-z_]\w*)+)(\s+as\s+)')
pat_blade = re.compile(r'(@foreach\s*\()(\$[A-Za-z_]\w*(?:->[A-Za-z_]\w*)+)(\s+as\s+)')
pat_forelse = re.compile(r'(@forelse\s*\()(\$[A-Za-z_]\w*(?:->[A-Za-z_]\w*)+)(\s+as\s+)')

n_raw = n_blade = n_forelse = 0

def wrap_raw(m):
    global n_raw
    # Skip if already coalesced — idempotent
    if '??' in m.group(2):
        return m.group(0)
    n_raw += 1
    return f"{m.group(1)}({m.group(2)} ?? []){m.group(3)}"

def wrap_blade(m):
    global n_blade
    if '??' in m.group(2):
        return m.group(0)
    n_blade += 1
    return f"{m.group(1)}({m.group(2)} ?? []){m.group(3)}"

def wrap_forelse(m):
    global n_forelse
    if '??' in m.group(2):
        return m.group(0)
    n_forelse += 1
    return f"{m.group(1)}({m.group(2)} ?? []){m.group(3)}"

new = pat_raw.sub(wrap_raw, src)
new = pat_blade.sub(wrap_blade, new)
new = pat_forelse.sub(wrap_forelse, new)

print(f"raw  foreach guarded: {n_raw}")
print(f"@foreach guarded:     {n_blade}")
print(f"@forelse guarded:     {n_forelse}")

if new != src:
    target.write_text(new, encoding='utf-8', newline='')
    print(f"wrote: {target}")
else:
    print("no change")
