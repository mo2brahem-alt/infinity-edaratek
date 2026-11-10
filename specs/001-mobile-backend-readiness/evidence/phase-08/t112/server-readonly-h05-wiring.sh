#!/usr/bin/env bash
# Targeted handler mapping only. Stdout; no PHP/HTTP/FCGI execution or changes.
set -eu
command -v python3 >/dev/null || { printf 'WIRING_PROBE=PYTHON3_UNAVAILABLE\n'; exit 41; }
python3 -I -B <<'H05_WIRING'
import json, pathlib as P, re, shlex, urllib.parse
vhost = P.Path('/etc/apache2/sites-available/test.edaratek.com.conf')
base = P.Path('/etc/php/8.3')
out = {'target': 'test.edaratek.com', 'wiring': [], 'linked_pools': []}
def read(p, root):
    try:
        q = p.resolve(strict=True)
        return q.read_text() if (q == root or root in q.parents) and q.stat().st_size < 131072 else ''
    except (OSError, UnicodeError, RuntimeError): return ''
def socket_path(value):
    m = re.search(r'unix:([^|\s]+)', value)
    return m[1] if m and re.fullmatch(r'/(?:run|var/run|var/php-fpm|home/test)/[A-Za-z0-9_./-]+\.sock', m[1]) else None
def backend(value):
    try:
        u = urllib.parse.urlsplit(value.split('|')[-1])
        return {'scheme': u.scheme, 'host': u.hostname, 'port': u.port} if u.hostname in {'localhost', '127.0.0.1', '::1'} and u.scheme in {'fcgi','http','https'} else 'NONLOCAL_OR_UNRESOLVED_NOT_CONTACTED'
    except ValueError: return 'UNRESOLVED'
def error_values(text):
    result = {}
    for line in text.splitlines():
        m = re.match(r'\s*(?:php_(?:admin_)?(?:value|flag)\[)?(display_errors|display_startup_errors|log_errors|error_reporting)\]?\s*=\s*([^;]+)', line)
        if m:
            v = m[2].strip().strip('\"\'')
            ok = (len(v) < 100 and re.fullmatch(r'(?:E_[A-Z_]+|[0-9~&|()+ -])+', v)) if m[1]=='error_reporting' else v.lower() in {'off','on','0','1','false','true','stderr','stdout'}
            result[m[1]] = v if ok else 'SUPPRESSED_OR_UNRESOLVED'
    return result
text, sockets = read(vhost, vhost), set()
for block in re.finditer(r'<VirtualHost\b[^>]*>(.*?)</VirtualHost\s*>', text, re.I|re.S):
    if not re.search(r'^\s*ServerName\s+test\.edaratek\.com(?::\d+)?\s*$', block[1], re.I|re.M): continue
    for n, line in enumerate(block[1].splitlines(), 1+text[:block.start(1)].count('\n')):
        try: parts = shlex.split(line, comments=True)
        except ValueError: continue
        if not parts: continue
        key = parts[0].lower()
        if key not in {'proxypass','proxypassmatch','sethandler','fcgidwrapper','fcgiwrapper','action','addhandler','addtype','include','includeoptional'}: continue
        row = {'line': n, 'directive': key}
        value = parts[-1] if len(parts)>1 else ''
        if key.startswith('proxypass') and ((len(parts)==2 and parts[1]=='!') or (len(parts)>2 and parts[2]=='!')): row['kind']='PROXY_EXCLUSION_NOT_FPM_MAPPING'
        elif key.startswith('proxypass') or key=='sethandler':
            value = parts[2] if key.startswith('proxypass') and len(parts)>2 else value
            sock = socket_path(value)
            row.update({'socket': sock, 'backend': backend(value)})
            if sock: sockets.add(sock)
        elif key in {'fcgidwrapper','fcgiwrapper','action'} and len(parts)>1:
            wrapper = P.Path(parts[2] if key=='action' and len(parts)>2 else parts[1])
            body = read(wrapper, P.Path('/home/test/fcgi-bin')) or read(wrapper, P.Path('/home/test/cgi-bin'))
            row['wrapper_inspected'] = bool(body)
            match = re.search(r'^\s*exec\s+(/(?:usr/)?(?:s?bin)/php-(?:cgi|fpm)(?:8\.3)?)\s*$', body, re.M)
            row['configured_executable'] = match[1] if match else 'UNKNOWN_NOT_EXECUTED'
            row['kind'] = 'CGI_WRAPPER_CONFIGURATION' if match and 'php-cgi' in match[1] else 'WRAPPER_REQUIRES_MAPPING'
            ini = re.search(r'^\s*(?:export\s+)?PHPRC=[\"\']?(/home/test/[A-Za-z0-9_./-]+)', body, re.M)
            if ini: row['wrapper_ini_error_settings'] = error_values(read(P.Path(ini[1])/'php.ini', P.Path('/home/test')))
        elif key in {'include','includeoptional'}: row['kind']='INDIRECT_CONFIG_UNRESOLVED_NO_RECURSIVE_SEARCH'
        else: row['handler_type'] = value if value in {'.php','fcgid-script','application/x-httpd-php','application/x-httpd-php8.3'} else 'OPERAND_SUPPRESSED'
        out['wiring'].append(row)
for name in {'test','test.edaratek.com',*[P.Path(s).stem for s in sockets]}:
    file = base/'fpm/pool.d'/(name+'.conf')
    if not re.fullmatch(r'[A-Za-z0-9_.-]+', name): continue
    chunks = re.split(r'(?m)^\s*\[([^\]\r\n]+)\]\s*$', read(file, base))
    for i in range(1,len(chunks)-1,2):
        fields = dict(re.findall(r'(?m)^\s*(user|listen)\s*=\s*([^;\r\n]+)',chunks[i+1]))
        if fields.get('user','').strip()=='test' and fields.get('listen','').strip() in sockets:
            ini_files = [base/'fpm/php.ini', *sorted((base/'fpm/conf.d').glob('*.ini'))]
            out['linked_pools'].append({'file':str(file),'listen':fields['listen'].strip(),'pool_error_settings':error_values(chunks[i+1]),
                'ini_error_settings':[{'file':str(f),'values':error_values(read(f,base))} for f in ini_files if error_values(read(f,base))]})
out.update({'scope':'Configured linkage only; empty matches do not prove FPM absent. No directives or backends executed.',
    'FPM_RUNTIME_DEBUG_VALUE':'UNKNOWN','FPM_DISPLAY_ERRORS':'UNKNOWN','RUNTIME_ERROR_DISCLOSURE_EVIDENCE':'UNAVAILABLE',
    'H05_STATUS':'BLOCKED','NO_WRITES':'YES','NO_SERVICE_RESTARTS':'YES','NO_LARAVEL_BOOT':'YES','NO_CONFIG_CHANGES':'YES',
    'NO_ENV_DUMP':'YES','NO_DEPLOYMENT':'YES','PRODUCTION_TOUCHED':'NO','PHASE_9_STARTED':'NO'})
print(json.dumps(out,indent=2))
H05_WIRING
