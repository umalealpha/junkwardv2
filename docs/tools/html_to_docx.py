# -*- coding: utf-8 -*-
"""Render an RI-* status HTML page as a Word document in Alpha Direct house style.

The HTML is the source of truth. This walks it rather than restating it, so the
two cannot drift the way the previous hard-coded generator did.

    py html_to_docx.py <in.html> <out.docx> <docref>
"""
import io
import re
import sys
from html.parser import HTMLParser

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

NAVY = RGBColor(0x0D, 0x1B, 0x2A)
ORANGE = RGBColor(0xF4, 0xA6, 0x23)
BODY = RGBColor(0x37, 0x42, 0x4E)
MUTED = RGBColor(0x6B, 0x77, 0x85)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
SERIF = 'Book Antiqua'

SRC, OUT, DOCREF = sys.argv[1], sys.argv[2], sys.argv[3]

doc = Document()
sec = doc.sections[0]
sec.top_margin = Cm(2.2)
sec.bottom_margin = Cm(2.2)
sec.left_margin = Cm(2.4)
sec.right_margin = Cm(2.4)

st = doc.styles['Normal']
st.font.name = SERIF
st.font.size = Pt(10.5)
st.font.color.rgb = BODY
st.element.rPr.rFonts.set(qn('w:eastAsia'), SERIF)
st.paragraph_format.space_after = Pt(6)
st.paragraph_format.line_spacing = 1.12


def shade(cell, hexcolor):
    el = OxmlElement('w:shd')
    el.set(qn('w:val'), 'clear')
    el.set(qn('w:fill'), hexcolor)
    cell._tc.get_or_add_tcPr().append(el)


def run(p, text, bold=False, size=10.5, color=BODY, italic=False, caps=False):
    r = p.add_run(text)
    r.font.name = SERIF
    r.font.size = Pt(size)
    r.font.bold = bold
    r.font.italic = italic
    r.font.color.rgb = color
    r.font.all_caps = caps
    r._element.rPr.rFonts.set(qn('w:eastAsia'), SERIF)
    return r


def runs(p, parts, size=10.5, color=BODY):
    for txt, bold, italic in parts:
        if txt:
            run(p, txt, bold=bold, size=size, color=color, italic=italic)


# ── HTML → [(text, bold, italic)] ──────────────────────────────────────────
class Inline(HTMLParser):
    """Flatten a fragment to runs, keeping strong/em and dropping everything else."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.parts = []
        self.b = 0
        self.i = 0

    def handle_starttag(self, tag, attrs):
        if tag in ('strong', 'b'):
            self.b += 1
        elif tag in ('em', 'i'):
            # In these pages <em> is an aside on its own line, so give it air —
            # otherwise it runs straight on from the sentence before it.
            self.parts.append(('   ', False, False))
            self.i += 1
        elif tag == 'br':
            self.parts.append((' — ', False, False))

    def handle_endtag(self, tag):
        if tag in ('strong', 'b'):
            self.b = max(0, self.b - 1)
        elif tag in ('em', 'i'):
            self.i = max(0, self.i - 1)

    def handle_data(self, data):
        if data.strip() or (self.parts and not self.parts[-1][0].endswith(' ')):
            self.parts.append((data, self.b > 0, self.i > 0))


def inline(fragment):
    p = Inline()
    p.feed(fragment)
    out = []
    for txt, b, i in p.parts:
        txt = re.sub(r'\s+', ' ', txt)
        if not txt.strip() and not out:
            continue
        if out and out[-1][1] == b and out[-1][2] == i:
            out[-1] = (out[-1][0] + txt, b, i)
        else:
            out.append((txt, b, i))
    while out and not out[0][0].strip():
        out.pop(0)
    while out and not out[-1][0].strip():
        out.pop()
    if out:
        out[0] = (out[0][0].lstrip(), out[0][1], out[0][2])
        out[-1] = (out[-1][0].rstrip(), out[-1][1], out[-1][2])
    return out


def flat(fragment):
    return ''.join(t for t, _, _ in inline(fragment))


# ── block writers ──────────────────────────────────────────────────────────
def h1(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(4)
    run(p, text, size=22, color=NAVY)


def h2(num, text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(20)
    p.paragraph_format.space_after = Pt(6)
    run(p, num + '   ', bold=True, size=9, color=ORANGE)
    run(p, text, size=15, color=NAVY)


def h3(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(14)
    p.paragraph_format.space_after = Pt(4)
    run(p, text, bold=True, size=9, color=MUTED, caps=True)
    pr = p._p.get_or_add_pPr()
    b = OxmlElement('w:pBdr')
    bo = OxmlElement('w:bottom')
    bo.set(qn('w:val'), 'single')
    bo.set(qn('w:sz'), '4')
    bo.set(qn('w:space'), '2')
    bo.set(qn('w:color'), 'DCE2E8')
    b.append(bo)
    pr.append(b)


def para(parts, size=10.5, color=BODY, after=6, before=0):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(after)
    p.paragraph_format.space_before = Pt(before)
    runs(p, parts, size=size, color=color)


def bullet(parts):
    p = doc.add_paragraph(style='List Bullet')
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.left_indent = Cm(0.6)
    runs(p, parts, size=10.5)


def table(headers, rows, widths=None):
    t = doc.add_table(rows=1, cols=len(headers))
    t.style = 'Table Grid'
    t.alignment = WD_TABLE_ALIGNMENT.LEFT
    hdr = t.rows[0].cells
    for i, htxt in enumerate(headers):
        shade(hdr[i], '0D1B2A')
        p = hdr[i].paragraphs[0]
        p.paragraph_format.space_after = Pt(2)
        p.paragraph_format.space_before = Pt(2)
        run(p, htxt, bold=True, size=8.5, color=WHITE, caps=True)
    for r in rows:
        cells = t.add_row().cells
        for i, val in enumerate(r[:len(headers)]):
            p = cells[i].paragraphs[0]
            p.paragraph_format.space_after = Pt(3)
            p.paragraph_format.space_before = Pt(3)
            runs(p, val, size=9.5)
    if widths:
        for row in t.rows:
            for i, w in enumerate(widths):
                row.cells[i].width = Cm(w)
    doc.add_paragraph().paragraph_format.space_after = Pt(2)


def callout(title, paragraphs):
    t = doc.add_table(rows=1, cols=1)
    t.style = 'Table Grid'
    c = t.rows[0].cells[0]
    shade(c, 'F7F8FA')
    p = c.paragraphs[0]
    p.paragraph_format.space_after = Pt(4)
    run(p, title, bold=True, size=9, color=ORANGE, caps=True)
    for parts in paragraphs:
        pp = c.add_paragraph()
        pp.paragraph_format.space_after = Pt(4)
        runs(pp, parts, size=9.5)
    doc.add_paragraph().paragraph_format.space_after = Pt(2)


# ── walk the page ──────────────────────────────────────────────────────────
s = io.open(SRC, encoding='utf-8').read()
body = s[s.index('<div class="wrap">'):]

title = flat(re.search(r'<h1[^>]*>(.*?)</h1>', body, re.S).group(1))
lede = re.search(r'</h1>\s*<p class="lede"[^>]*>(.*?)</p>', body, re.S)
meta = re.findall(r'<span><b>([^<]+)</b>\s*([^<]*)</span>', body)
tiles = re.findall(r'<div class="n[^"]*">(.*?)</div>\s*<div class="l">(.*?)</div>', body, re.S)

p = doc.add_paragraph()
p.paragraph_format.space_after = Pt(2)
run(p, DOCREF, bold=True, size=8.5, color=ORANGE, caps=True)
run(p, '     ALPHA DIRECT INSURANCE COMPANY (PTY) LTD     GABORONE', size=8.5, color=MUTED)

h1(title)
if lede:
    para(inline(lede.group(1)), size=11, after=8)

p = doc.add_paragraph()
p.paragraph_format.space_after = Pt(2)
for label, val in meta:
    run(p, label + '  ', bold=True, size=8.5, color=MUTED)
    run(p, val.strip() + '     ', size=8.5, color=BODY)

if tiles:
    table([flat(l) for _, l in tiles], [[inline(n) for n, _ in tiles]])

BLOCK = re.compile(
    r'<h2[^>]*>(?P<h2>.*?)</h2>'
    r'|<h3[^>]*>(?P<h3>.*?)</h3>'
    r'|<p class="lede"[^>]*>(?P<lede>.*?)</p>'
    r'|<ul class="points"[^>]*>(?P<ul>.*?)</ul>'
    # These three all close on their last paragraph, so anchor on that rather
    # than on </div></div> — the inner .hd/.k/.t divers would swallow the match.
    r'|<div class="callout"[^>]*>(?P<callout>.*?)</p>\s*</div>'
    r'|<table>(?P<table>.*?)</table>'
    r'|<div class="entry"[^>]*>(?P<entry>.*?)</p>\s*</div>'
    r'|<div class="rec"[^>]*>(?P<rec>.*?)</p>\s*</div>'
    r'|<footer>(?P<footer>.*?)</footer>',
    re.S)

after_head = body[body.index('</h1>'):]
seen_first_h2 = False
entries = []


def flush_entries():
    global entries
    if entries:
        table(['Day', 'What was delivered'], entries, [3.4, 12.6])
        entries = []


for m in BLOCK.finditer(after_head):
    kind = m.lastgroup
    frag = m.group(kind)

    if kind != 'entry':
        flush_entries()

    # The outer pattern anchors on the block's last </p>, so put it back before
    # picking the paragraphs out again.
    if kind in ('callout', 'entry', 'rec'):
        frag = frag + '</p>'

    if kind == 'h2':
        num = re.search(r'<span class="num">(.*?)</span>', frag)
        h2(flat(num.group(1)) if num else '', flat(re.sub(r'<span class="num">.*?</span>', '', frag, flags=re.S)))
        seen_first_h2 = True
    elif kind == 'h3':
        h3(flat(frag))
    elif kind == 'lede':
        if seen_first_h2:
            para(inline(frag), color=MUTED, after=8)
    elif kind == 'ul':
        for li in re.findall(r'<li[^>]*>(.*?)</li>', frag, re.S):
            bullet(inline(li))
    elif kind == 'callout':
        hd = re.search(r'<div class="hd">(.*?)</div>', frag, re.S)
        ps = re.findall(r'<p[^>]*>(.*?)</p>', frag, re.S)
        callout(flat(hd.group(1)) if hd else '', [inline(x) for x in ps])
    elif kind == 'table':
        heads = [flat(x) for x in re.findall(r'<th[^>]*>(.*?)</th>', frag, re.S)]
        rows = [[inline(c) for c in re.findall(r'<td[^>]*>(.*?)</td>', tr, re.S)]
                for tr in re.findall(r'<tr>(.*?)</tr>', frag.split('<tbody>')[-1], re.S)]
        rows = [r for r in rows if r]
        if heads and rows:
            n = len(heads)
            width = 16.0
            first = 2.0 if n > 3 else 3.6
            rest = (width - first) / (n - 1)
            table(heads, rows, [first] + [rest] * (n - 1))
    elif kind == 'entry':
        # The outer pattern already ate the closing </p>, so take the remainder.
        day = re.search(r'<div class="day">(.*?)</div>', frag, re.S)
        what = re.search(r'<p class="what">(.*?)</p>', frag, re.S)
        label = day.group(1).replace('<span>', ' &middot; ') if day else ''
        entries.append([inline(label),
                        inline(what.group(1)) if what else []])
    elif kind == 'rec':
        k = re.search(r'<div class="k">(.*?)</div>', frag, re.S)
        t = re.search(r'<div class="t">(.*?)</div>', frag, re.S)
        b = re.search(r'<p>(.*?)</p>', frag, re.S)
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(10)
        p.paragraph_format.space_after = Pt(2)
        run(p, (flat(k.group(1)) if k else '') + '   ', bold=True, size=9, color=ORANGE)
        run(p, flat(t.group(1)) if t else '', bold=True, size=11, color=NAVY)
        if b:
            para(inline(b.group(1)), size=10, after=4)
    elif kind == 'footer':
        for x in re.findall(r'<p>(.*?)</p>', frag, re.S):
            para(inline(x), size=8.5, color=MUTED, after=3, before=6)

flush_entries()

doc.save(OUT)
print('saved:', OUT)
