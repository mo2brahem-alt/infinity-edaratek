#!/usr/bin/env bash
# Read only explicitly referenced test CGI wrapper/INI files. No PHP execution.
set -eu
command -v python3 >/dev/null || { printf 'CGI_PROBE=PYTHON3_UNAVAILABLE\n'; exit 41; }
python3 -I -B <<'H05_CGI'
import json, pathlib as P, re, shlex
root, php = P.Path('/home/test'), P.Path('/etc/php/8.3')
vhost = P.Path('/etc/apache2/sites-available/test.edaratek.com.conf')
keys = {'display_errors','display_startup_errors','log_errors','error_reporting'}
def read(p, roots):
    try:
        q = p.resolve(strict=True)
        return q.read_text() if any(q==r or r in q.parents for r in roots) and q.stat().st_size<131072 else ''
    except (OSError, UnicodeError, RuntimeError): return ''
def path_value(value, scan=False):
    if scan and value=='': return 'DEFAULT_SCAN_UNRESOLVED'
    if not re.fullmatch(r'/[A-Za-z0-9_./:-]+',value): return 'UNKNOWN_DYNAMIC_OR_SUPPRESSED'
    return value if all(x.startswith(('/home/test/','/etc/php/8.3/')) or (scan and x=='') for x in value.split(':')) else 'OUTSIDE_TEST_SCOPE_NOT_READ'
def setting(value,key):
    value=value.strip().strip('\"\'')
    if key=='error_reporting':
        return value if len(value)<100 and re.fullmatch(r'(?:E_[A-Z_]+|[0-9~&|()+ -])+',value) else 'UNKNOWN_OR_SUPPRESSED'
    return value if value.lower() in {'off','on','0','1','false','true','yes','no','stderr','stdout',''} else 'UNKNOWN_OR_SUPPRESSED'
def ini_values(text):
    result=[]; conditional=False
    for line in text.splitlines():
        if line.lstrip().startswith('['): conditional=bool(re.match(r'\s*\[(?:PATH|HOST)\s*=',line,re.I))
        m=re.match(r'\s*(display_errors|display_startup_errors|log_errors|error_reporting)\s*=\s*([^;]*)',line)
        if m: result.append({'key':m[1],'value':setting(m[2],m[1]),'conditional_path_or_host_section':conditional})
    return result
wrappers=set()
for block in re.findall(r'<VirtualHost\b[^>]*>(.*?)</VirtualHost\s*>',read(vhost,[vhost]),re.I|re.S):
    if not re.search(r'^\s*ServerName\s+test\.edaratek\.com(?::\d+)?\s*$',block,re.M|re.I): continue
    for line in block.splitlines():
        try: fields=shlex.split(line,comments=True)
        except ValueError: continue
        if len(fields)>1 and fields[0].lower() in {'fcgiwrapper','fcgidwrapper'} and re.fullmatch(r'/home/test/(?:fcgi-bin|cgi-bin)/[A-Za-z0-9_.-]+',fields[1]): wrappers.add(fields[1])
records=[]
for wrapper in sorted(wrappers):
    body=read(P.Path(wrapper),[root/'fcgi-bin',root/'cgi-bin'])
    env={}; cli=[]; env_mentions=[]; executable='UNKNOWN'; notes=[]
    for line in body.replace('\\\n',' ').splitlines():
        try: fields=shlex.split(line,comments=True)
        except ValueError: notes.append('UNRESOLVED_SHELL_QUOTING'); continue
        if not fields: continue
        if fields[0]=='export': fields=fields[1:]
        prefix=fields[2:] if fields[:2]==['exec','env'] else fields[1:] if fields[:1]==['env'] else fields
        assignments=[]
        for item in prefix:
            if not re.match(r'^[A-Za-z_][A-Za-z0-9_]*=',item): break
            assignments.append(item)
        for item in assignments:
            m=re.fullmatch(r'(PHPRC|PHP_INI_SCAN_DIR)=(.*)',item,re.S)
            if m: env[m[1]]=m[2]
            for key in keys:
                if item.startswith(key+'='): env_mentions.append({'key':key,'value':setting(item.split('=',1)[1],key),'application_semantics':'UNPROVEN_ENV_MENTION'})
            if item.startswith(('PHP_VALUE=','PHP_ADMIN_VALUE=')):
                env_mentions.extend(dict(row,application_semantics='UNPROVEN_PHP_VALUE_ENV_MENTION') for row in ini_values(item.split('=',1)[1]))
        if fields and fields[0]=='exec':
            fields=fields[1:]
            if fields and fields[0]=='env': fields=fields[1:]
            while fields and re.match(r'^[A-Za-z_][A-Za-z0-9_]*=',fields[0]): fields=fields[1:]
            if not fields or fields[0] not in {'/bin/php-cgi8.3','/usr/bin/php-cgi8.3'}: notes.append('UNRESOLVED_EXECUTABLE'); continue
            executable=fields[0]; args=iter(fields[1:])
            for arg in args:
                if arg in {'-c','--php-ini','-n','--no-php-ini'} or arg.startswith(('-c','--php-ini=')): notes.append('CONFIG_SELECTION_OVERRIDE_PRESENT_NOT_EVALUATED')
                value=next(args,'') if arg in {'-d','--define'} else arg[2:] if arg.startswith('-d') else arg[9:] if arg.startswith('--define=') else None
                if value is not None:
                    key,sep,val=value.partition('=')
                    if key in keys: cli.append({'key':key,'value':setting(val if sep else '1',key)})
    # Extract only four INI directives from quoted PHP_VALUE/PHP_ADMIN_VALUE,
    # including multiline literals. Never print other environment assignments.
    for m in re.finditer(r'(?m)^\s*(?:export\s+)?PHP_(?:ADMIN_)?VALUE=([\"\'])(.*?)\1',body,re.S):
        env_mentions.extend(dict(row,application_semantics='UNPROVEN_PHP_VALUE_ENV_MENTION') for row in ini_values(m[2]))
    sources=[]
    phprc=path_value(env['PHPRC']) if 'PHPRC' in env else 'UNSET_IN_WRAPPER_INHERITANCE_UNKNOWN'
    scan=path_value(env['PHP_INI_SCAN_DIR'],True) if 'PHP_INI_SCAN_DIR' in env else 'UNSET_IN_WRAPPER_DEFAULT_UNKNOWN'
    if phprc.startswith('/'):
        p=P.Path(phprc)
        try:
            q=p.resolve(strict=True)
            if any(q==r or r in q.parents for r in [root,php]):
                p=q/'php.ini' if q.is_dir() else q
                text=read(p,[root,php]); sources.append({'file':str(p),'readable':bool(text),'settings':ini_values(text),'basis':'PHPRC_REFERENCE_NOT_PROOF_OF_LOADING'})
        except (OSError,RuntimeError): notes.append('PHPRC_TARGET_UNAVAILABLE')
    if scan.startswith('/'):
        for directory in scan.split(':'):
            if not directory: notes.append('COMPILED_DEFAULT_SCAN_UNKNOWN'); continue
            try:
                q=P.Path(directory).resolve(strict=True)
                if not any(q==r or r in q.parents for r in [root,php]): notes.append('EXTERNAL_SCAN_TARGET_NOT_READ'); continue
                files=sorted(q.glob('*.ini'))
                if len(files)>64: notes.append('SCAN_LIMIT_INCOMPLETE')
                for file in files[:64]:
                    text=read(file,[root,php]); values=ini_values(text)
                    if values: sources.append({'file':str(file),'settings':values,'basis':'DECLARED_SCAN_ORDER_NOT_PROOF_OF_LOADING'})
            except (OSError,RuntimeError): notes.append('SCAN_TARGET_UNAVAILABLE')
    records.append({'wrapper':wrapper,'wrapper_readable':bool(body),'executable':executable,'PHPRC':phprc,'PHP_INI_SCAN_DIR':scan,
        'ini_sources':sources,'command_line_defines':cli,'related_environment_mentions':env_mentions,'notes':sorted(set(notes))})
one=records[0] if len(records)==1 else {}
findings=[]
for record in records:
    groups=[(s['file'],s['settings']) for s in record['ini_sources']]+[('COMMAND_LINE',record['command_line_defines']),('ENV_MENTION_UNPROVEN',record['related_environment_mentions'])]
    for source,rows in groups:
        for row in rows:
            if row['key'] in {'display_errors','display_startup_errors'} and row['value'].lower() in {'on','1','true','yes','stdout','stderr'}:
                findings.append({'source':source,'key':row['key'],'value':row['value'],'finding':'ENABLED_ERROR_DISPLAY_SOURCE_VALUE_LOADING_PRECEDENCE_UNPROVEN'})
out={'PHP_HANDLER':'CGI_WRAPPER','PHP_EXECUTABLE':one.get('executable','UNKNOWN'),'FPM_NOT_APPLICABLE_FOR_THIS_VHOST':'YES',
    'CGI_WRAPPER_PATH':one.get('wrapper','UNKNOWN_OR_MULTIPLE'),'CGI_PHPRC':one.get('PHPRC','UNKNOWN'),
    'CGI_PHP_INI_SCAN_DIR':one.get('PHP_INI_SCAN_DIR','UNKNOWN'),
    'CGI_DISPLAY_ERRORS':'UNKNOWN','CGI_DISPLAY_STARTUP_ERRORS':'UNKNOWN','CGI_LOG_ERRORS':'UNKNOWN','CGI_ERROR_REPORTING':'UNKNOWN',
    'APP_DEBUG_CACHED_VALUE':True,'RUNTIME_ERROR_DISCLOSURE_EVIDENCE':'UNAVAILABLE','H05_STATUS':'BLOCKED',
    'static_configuration_evidence':records,'CGI_CONFIGURATION_FINDINGS':findings,'limits':['Literal source mentions only; no shell expansion/evaluation or CGI execution.',
    'INI loading, inherited/default/per-request/application overrides remain unverified. PHP display_errors is not Laravel app.debug.',
    'Enabled display-error source values are findings to review, not evidence of equivalent protection. No safe error endpoint invoked.'],
    'NO_WRITES':'YES','NO_PHP_EXECUTION':'YES','NO_LARAVEL_BOOT':'YES','NO_HTTP_OR_FCGI_REQUESTS':'YES',
    'NO_CONFIG_CHANGES':'YES','NO_SERVICE_RESTARTS':'YES','NO_ENV_DUMP':'YES','NO_DEPLOYMENT':'YES',
    'PRODUCTION_TOUCHED':'NO','REAL_FILES_MIGRATED':0,'REAL_WHATSAPP_SENDS':0,'PHASE_9_STARTED':'NO'}
print(json.dumps(out,indent=2))
H05_CGI
