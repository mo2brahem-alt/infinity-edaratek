#!/usr/bin/env bash
# H05 only. Paste this file into the trusted SSH session; stdout only.
# No PHP/Laravel execution, HTTP calls, environment dump, files or service changes.
set -eu
command -v python3 >/dev/null || { printf 'H05_PROBE=BLOCKED_PYTHON3_UNAVAILABLE\n'; exit 41; }
python3 -I -B <<'H05_PYTHON'
import datetime, json, os, pathlib, pwd, re, subprocess

target = 'test.edaratek.com'
vhost = pathlib.Path('/etc/apache2/sites-available/test.edaratek.com.conf')
base = pathlib.Path('/etc/php/8.3')
out = {'target': target, 'timestamp_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
       'scope': 'H05 target vhost, associated PHP8.3 FPM pools and four error INI settings only'}
settings = {'display_errors', 'display_startup_errors', 'error_reporting', 'log_errors'}
def safe(value, key='section'):
    value = value.split(';', 1)[0].strip().strip('\"\'')
    if key == 'section':
        return value if re.fullmatch(r'[A-Za-z0-9_.-]{1,100}', value) else 'VALUE_SUPPRESSED_OR_UNRESOLVED'
    if key in {'APP_ENV', 'env[APP_ENV]'}:
        return value if value.lower() in {'production', 'staging', 'testing', 'local', 'development'} else 'VALUE_SUPPRESSED_OR_UNRESOLVED'
    if 'error_reporting' in key:
        return value if len(value) <= 120 and re.fullmatch(r'(?:E_[A-Z_]+|[0-9~&|()+ -])+', value) else 'VALUE_SUPPRESSED_OR_UNRESOLVED'
    return value if value.lower() in {'on', 'off', 'true', 'false', 'yes', 'no', '0', '1', 'stdout', 'stderr'} else 'VALUE_SUPPRESSED_OR_UNRESOLVED'

def read(path, roots):
    try:
        resolved = path.resolve(strict=True)
        if not any(resolved == root or root in resolved.parents for root in roots):
            return None
        if not resolved.is_file() or resolved.stat().st_size > 262144:
            return None
        return resolved.read_text(errors='replace')
    except (OSError, RuntimeError):
        return None

text = read(vhost, [vhost])
if text is None:
    print(json.dumps({'H05_PROBE': 'BLOCKED_TARGET_VHOST_UNREADABLE_OR_SYMLINK'}, indent=2))
    raise SystemExit(42)
enabled = pathlib.Path('/etc/apache2/sites-enabled/test.edaratek.com.conf')
out['enabled_site_link_matches_candidate'] = enabled.is_symlink() and enabled.resolve() == vhost
blocks = re.findall(r'<VirtualHost\b[^>]*>(.*?)</VirtualHost\s*>', text, re.I | re.S)
selected = [b for b in blocks if re.search(r'^\s*ServerName\s+test\.edaratek\.com(?::\d+)?\s*$', b, re.M | re.I)]
if not selected:
    print(json.dumps({'H05_PROBE': 'BLOCKED_EXACT_TEST_VHOST_NOT_FOUND'}, indent=2))
    raise SystemExit(43)
sockets, apache = set(), []
for block_number, block in enumerate(selected, 1):
    for line in block.splitlines():
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        directive = line.split(None, 1)[0].lower()
        if directive in {'sethandler', 'proxypass', 'proxypassmatch', 'action'}:
            row = {'block': block_number, 'directive': directive, 'raw_operand_suppressed': True}
            for socket in re.findall(r'(?:proxy:)?unix:([^|\s\"\']+)', line):
                p = pathlib.Path(socket)
                if re.fullmatch(r'/[A-Za-z0-9_./-]+\.sock', socket) and any(r == p or r in p.parents for r in
                        [pathlib.Path('/run'), pathlib.Path('/var/run'), pathlib.Path('/var/php-fpm'), pathlib.Path('/home/test')]):
                    sockets.add(socket)
                    row['fcgi_socket'] = socket
            if 'fcgi://' in line and 'fcgi_socket' not in row:
                row['backend'] = 'FCGI_TARGET_REQUIRES_OPERATOR_MAPPING_NO_CONNECTION_ATTEMPTED'
            apache.append(row)
        elif directive in {'include', 'includeoptional'}:
            apache.append({'block': block_number, 'directive': directive,
                           'status': 'INDIRECT_CONFIG_NOT_FOLLOWED_REQUIRES_TARGETED_OPERATOR_REVIEW'})
        elif directive in {'php_value', 'php_admin_value', 'php_flag', 'php_admin_flag'}:
            fields = line.split(None, 2)
            if len(fields) == 3 and fields[1] in settings:
                apache.append({'block': block_number, 'directive': directive, 'key': fields[1], 'value': safe(fields[2], fields[1])})
        elif directive == 'setenv':
            fields = line.split(None, 2)
            if len(fields) == 3 and fields[1] in {'APP_ENV', 'APP_DEBUG'}:
                apache.append({'block': block_number, 'directive': directive, 'key': fields[1], 'value': safe(fields[2], fields[1])})
        elif directive in {'passenv', 'setenvif', 'setenvifexpr', 'proxyfcgisetenvif'} and re.search(r'\bAPP_(?:ENV|DEBUG)\b', line):
            apache.append({'block': block_number, 'directive': directive, 'value': 'CONDITIONAL_OR_INHERITED_UNRESOLVED'})
out['target_apache_wiring_and_overrides'] = apache

# No global process/environment dump: only PHP8.3 FPM processes owned by test.
workers, names = [], {'test', target}
try:
    uid = pwd.getpwnam('test').pw_uid
    ps = subprocess.run(['ps', '-u', 'test', '-o', 'pid=,comm=,args='], capture_output=True, text=True, timeout=5)
    for line in ps.stdout.splitlines():
        parts = line.split(None, 2)
        if len(parts) != 3 or not parts[0].isdigit() or 'php-fpm' not in parts[1]:
            continue
        match = re.fullmatch(r'php-fpm: pool ([A-Za-z0-9_.-]+)', parts[2].strip())
        proc = pathlib.Path('/proc') / parts[0]
        if not match or proc.stat().st_uid != uid:
            continue
        binary = os.readlink(str(proc / 'exe'))
        if pathlib.Path(binary).name != 'php-fpm8.3':
            continue
        names.add(match[1])
        workers.append({'pid': int(parts[0]), 'user': 'test', 'pool_label': match[1], 'binary': binary})
except (OSError, KeyError, subprocess.SubprocessError):
    out['process_metadata'] = 'UNAVAILABLE_NO_RAW_ERROR_EXPORTED'
out['test_owned_php83_fpm_workers'] = workers
for socket in sockets:
    names.add(pathlib.Path(socket).stem)

# Exact derived candidates, not a recursive /etc dump or all-domain pool dump.
pools = []
for name in sorted(names):
    file = base / 'fpm/pool.d' / (name + '.conf')
    content = read(file, [base])
    if content is None:
        continue
    chunks = re.split(r'(?m)^\s*\[([^\]\r\n]+)\]\s*$', content)
    for i in range(1, len(chunks) - 1, 2):
        values = {}
        for line in chunks[i+1].splitlines():
            if '=' in line and not line.lstrip().startswith(';'):
                key, value = line.split('=', 1)
                values[key.strip()] = value.split(';', 1)[0].strip().strip('\"\'')
        if values.get('user') != 'test' or values.get('listen') not in sockets:
            continue
        overrides = {}
        for key, value in values.items():
            match = re.fullmatch(r'(?:php_admin_value|php_value|php_admin_flag|php_flag)\[([^]]+)\]', key)
            if (match and match[1] in settings) or key in {'env[APP_ENV]', 'env[APP_DEBUG]', 'clear_env'}:
                overrides[key] = safe(value, key)
        pools.append({'file': str(file), 'section': safe(chunks[i]), 'user': 'test',
                      'listen': values['listen'], 'configured_overrides': overrides})
out['socket_linked_test_pools'] = pools
out['fpm_php_ini_error_settings'] = []
if pools:
    for file in [base / 'fpm/php.ini', *sorted((base / 'fpm/conf.d').glob('*.ini'))]:
        content = read(file, [base])
        if content is None:
            continue
        for line in content.splitlines():
            match = re.match(r'^\s*(display_errors|display_startup_errors|error_reporting|log_errors)\s*=\s*(.*?)\s*$', line)
            if match:
                out['fpm_php_ini_error_settings'].append({'file': str(file), 'key': match[1], 'value': safe(match[2], match[1])})
out.update({'H05_STATUS': 'BLOCKED', 'FPM_RUNTIME_DEBUG_VALUE': 'UNKNOWN', 'FPM_DISPLAY_ERRORS': 'UNKNOWN',
            'RUNTIME_ERROR_DISCLOSURE_EVIDENCE': 'UNAVAILABLE',
            'limits': ['Config files/process linkage are not live FPM request/application values.',
                       'PHP display_errors and Laravel app.debug are different controls.',
                       'No known dedicated safe error endpoint; no HTTP/FCGI request or manufactured exception.',
                       'Unresolved includes/pool mapping remain UNKNOWN; no broad fallback search.'],
            'NO_CONFIG_CHANGES': 'YES', 'NO_APPLICATION_CHANGES': 'YES', 'NO_DEPLOYMENT': 'YES',
            'NO_SERVICE_RESTARTS': 'YES', 'PRODUCTION_TOUCHED': 'NO', 'REAL_FILES_MIGRATED': 0,
            'REAL_WHATSAPP_SENDS': 0, 'PHASE_9_STARTED': 'NO'})
print(json.dumps(out, indent=2))
H05_PYTHON
