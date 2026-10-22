"""Review source contracts without claiming PHP execution or browser validation."""
from pathlib import Path
from collections import Counter
from html.parser import HTMLParser
import argparse, hashlib, json, re, subprocess, zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--baseline', required=True, type=Path, help='Unmodified supplied ZIP with aquapure/ at its root')
parser.add_argument('--report', type=Path, help='Optional JSON output path')
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
archive = args.baseline
with zipfile.ZipFile(archive) as z:
    baseline = {n.removeprefix('aquapure/'): z.read(n) for n in z.namelist() if not n.endswith('/')}
current = {p.relative_to(root).as_posix(): p.read_bytes() for p in root.rglob('*') if p.is_file() and '.git' not in p.parts}
php = sorted(n for n in current if n.endswith('.php') and not n.startswith('tests/'))
changed = sorted(n for n in baseline if current.get(n) != baseline[n])
issues = []

def php_blocks(s):
    return re.findall(r'<\?(?:php|=)(.*?)(?:\?>|\Z)', s, re.S)

def php_tokens(s):
    # Lexical scanner only: it is not a substitute for php -l.
    strings = []; stack = []
    for block in php_blocks(s):
        i = 0
        while i < len(block):
            if block.startswith('//', i) or block[i] == '#':
                end = block.find('\n', i); i = len(block) if end == -1 else end + 1; continue
            if block.startswith('/*', i):
                end = block.find('*/', i+2)
                if end < 0: raise ValueError('Unterminated comment')
                i = end + 2; continue
            char = block[i]
            if char in "'\"":
                quote = char; start = i; i += 1
                while i < len(block):
                    if block[i] == '\\': i += 2; continue
                    if block[i] == quote: break
                    i += 1
                if i >= len(block): raise ValueError('Unterminated string')
                strings.append(block[start+1:i]); i += 1; continue
            if char in '([{': stack.append(char)
            elif char in ')]}':
                if not stack or stack.pop() != dict(zip(')]}', '([{'))[char]: raise ValueError('Unbalanced delimiters')
            i += 1
    if stack: raise ValueError('Unclosed delimiter')
    return strings

def sql_strings(source):
    return Counter(re.sub(r'\s+', ' ', x).strip() for x in php_tokens(source) if re.match(r'\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|SHOW)\s', x))

sql_count = 0
for name in php:
    try: php_tokens(current[name].decode())
    except ValueError as exc: issues.append(f'{name}: lexical check: {exc}')
    if name in baseline:
        old_sql = sql_strings(baseline[name].decode())
        new_sql = sql_strings(current[name].decode())
        sql_count += sum(old_sql.values())
        if old_sql != new_sql: issues.append(f'{name}: SQL literal difference')

class Forms(HTMLParser):
    def __init__(self, source):
        super().__init__(); self.fields=Counter(); self.forms=Counter(); self.tables=0; self.headings=0
        self.feed(source)
    def handle_starttag(self, tag, pairs):
        a = dict(pairs)
        if tag == 'form': self.forms[(a.get('method','get').lower(), a.get('action',''), a.get('enctype',''))] += 1
        if tag in ['input','select','textarea','button'] and a.get('name'):
            self.fields[(tag, a['name'], a.get('value','') if a['name']=='action' else '')] += 1
        if tag=='table': self.tables += 1
        if tag=='h1': self.headings += 1

def markup(source):
    def sub(m):
        code = re.sub(r'\s+', '', m.group()).replace('"', "'")
        return 'AQPHP' + hashlib.sha256(code.encode()).hexdigest()[:16]
    return re.sub(r'<\?(?:php|=).*?(?:\?>|\Z)', sub, source, flags=re.S)

form_count=0; field_count=0; table_count=0; csrf_count=0
for name in php:
    source=current[name].decode()
    new=Forms(markup(source)); table_count+=new.tables
    if name not in baseline or name in ['includes/app-footer.php','includes/portal-footer.php']: continue
    oldsource=baseline[name].decode(); old=Forms(markup(oldsource))
    form_count += sum(old.forms.values()); field_count += sum(old.fields.values())
    if old.forms != new.forms: issues.append(f'{name}: form method/action/enctype changed')
    if old.fields != new.fields: issues.append(f'{name}: named form controls changed: {old.fields-new.fields} / {new.fields-old.fields}')
    old_csrf=oldsource.count('csrf_field()'); new_csrf=source.count('csrf_field()'); csrf_count+=old_csrf
    if old_csrf!=new_csrf: issues.append(f'{name}: CSRF helper count changed')
    if 'pages/' in name:
        if source.count('<table')!=source.count('<caption'): issues.append(f'{name}: table caption missing')
        for th in re.findall(r'<thead>(.*?)</thead>', markup(source), re.S):
            if any('scope="col"' not in x for x in re.findall(r'<th\b[^>]*>',th)): issues.append(f'{name}: column scope missing')

# Shared confirmation markup replaced two identical footer copies.
modal=Forms(markup(current['includes/confirmation-modal.php'].decode()))
for name in ['includes/app-footer.php','includes/portal-footer.php']:
    old=Forms(markup(baseline[name].decode()))
    if old.forms != modal.forms or old.fields != modal.fields: issues.append(f'{name}: shared modal contract differs')
    if baseline[name].decode().count('csrf_field()') != current['includes/confirmation-modal.php'].decode().count('csrf_field()'): issues.append(f'{name}: shared modal CSRF differs')

protected = [n for n in baseline if n.startswith(('database/', 'config/', 'assets/vendor/')) or n in ['portal/index.php','customer/index.php','logout.php','includes/rbac.php','includes/invitations.php','includes/paymongo.php'] or (n.endswith('.php') and n.startswith(('admin/','staff/','customer/')) and '/pages/' not in n and not n.endswith('register.php')) or n.endswith('.htaccess')]
for name in protected:
    if baseline[name]!=current.get(name): issues.append(f'{name}: protected baseline file changed')

css=current['assets/css/style.css'].decode()
stripped=re.sub(r'/\*.*?\*/|"(?:\\.|[^"\\])*"|\'(?:\\.|[^\'\\])*\'', '',css,flags=re.S)
if stripped.count('{') != stripped.count('}'): issues.append('CSS braces unbalanced')
defined=set(re.findall(r'(--[\w-]+)\s*:',css))
used=set(re.findall(r'var\((--aq-[\w-]+)',css))
if used-defined: issues.append(f'Unresolved AquaPure CSS tokens: {used-defined}')
if re.search(r'(?:body|html)[^{]*\{[^}]*overflow-x:\s*hidden',css): issues.append('Global horizontal overflow suppression')

def luminance(h):
    channels=[int(h[i:i+2],16)/255 for i in (1,3,5)]
    return sum(w*(v/12.92 if v<=0.04045 else ((v+0.055)/1.055)**2.4) for w,v in zip([.2126,.7152,.0722],channels))
def contrast(a,b):
    x,y=sorted([luminance(a),luminance(b)]); return round((y+.05)/(x+.05),2)
pairs=[('Body','#172B3A','#FFFFFF'),('Muted','#526575','#FFFFFF'),('Primary button','#FFFFFF','#075EAD'),('Primary hover','#FFFFFF','#064B8A'),('Control edge on white','#75899A','#FFFFFF'),('Control edge on canvas','#75899A','#F4F7FA'),('Sidebar','#E7EFF5','#0B263B'),('Success','#166534','#DCFCE7'),('Warning / Partial','#92400E','#FEF3C7'),('Danger','#B91C1C','#FEE2E2'),('Progress','#075985','#E0F2FE'),('Ready for pickup','#0F615B','#E5F5F2'),('Neutral status','#526575','#EDF2F5'),('Footer muted','#BFCED8','#0B263B')]
ratios=[{'combination':label,'foreground':a,'background':b,'ratio':contrast(a,b)} for label,a,b in pairs]
try:
    js=subprocess.run(['node','--check',str(root/'assets/js/app.js')],capture_output=True,text=True)
except FileNotFoundError:
    js=subprocess.CompletedProcess(['node'], 1, '', 'Node.js is required for the JavaScript syntax check.')
if js.returncode: issues.append(js.stderr)
report={'baseline_sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'baseline_files':len(baseline),'baseline_php_files':sum(n.endswith('.php') for n in baseline),'current_application_php_files':len(php),'changed_baseline_files':changed,'unchanged_protected_files':len(protected),'compared_sql_literals':sql_count,'compared_forms_excluding_two_equivalent_modals':form_count,'compared_named_controls_excluding_modals':field_count,'csrf_helper_calls_excluding_modals':csrf_count,'table_templates':table_count,'javascript_syntax':'PASS' if js.returncode==0 else 'FAIL','php_lexical_delimiters':'PASS' if not any('lexical check' in x for x in issues) else 'FAIL','php_runtime':'NOT RUN: this script checks source only','browser_runtime':'NOT RUN: this script checks source only','database_runtime':'NOT RUN: this script checks source only','token_contrast':ratios,'issues':issues}
if args.report:
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report,indent=2))
raise SystemExit(bool(issues))
