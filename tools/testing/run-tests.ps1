[CmdletBinding()]
param([switch]$SelfTest, [switch]$PrepareOnly, [switch]$ValidateOnly)

$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$script:copied = 0
$script:skipped = 0

function Write-SafeBuildDiagnostics([object[]]$BuildOutput) {
    # Sanitize BEFORE truncation; never emit arbitrary environment or full logs.
    $text = ($BuildOutput | ForEach-Object { [string]$_ }) -join "`n"
    $text = $text -replace '\x1B\[[0-?]*[ -/]*[@-~]', ''
    $text = $text -replace '(?s)-----BEGIN [A-Z ]*PRIVATE KEY-----.*?(?:-----END [A-Z ]*PRIVATE KEY-----|$)', '[PRIVATE_KEY_REDACTED]'
    $text = $text -replace '(?im)\b(?:proxy-)?authorization\s*[:=].*$', 'Authorization: [REDACTED]'
    $text = $text -replace '(?i)\b(?:Bearer|Basic)\s+[A-Za-z0-9+/=._~-]+', '[AUTH_REDACTED]'
    $text = $text -replace '(?i)(\b[A-Za-z0-9_.-]{0,128}(?:password|passwd|secret|token|api[_-]?key|credential)[A-Za-z0-9_.-]{0,128}["'']?\s*[:=]\s*)(?:"[^"]*"|''[^'']*''|[^\s;,]+)', '$1[REDACTED]'
    $text = $text -replace '(?i)(--(?:password|secret|token|api-key)\s+)(?:"[^"]*"|''[^'']*''|\S+)', '$1[REDACTED]'
    $text = $text -replace '\b(?:gh[pousr]_[A-Za-z0-9_]+|github_pat_[A-Za-z0-9_]+|xox[baprs]-[A-Za-z0-9-]+|AKIA[A-Z0-9]{16}|eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\b', '[TOKEN_REDACTED]'
    $text = $text -replace '(?i)https?://[^\s"''<>]+', '[URL_REDACTED]'
    $text = $text -replace '\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b', '[EMAIL_REDACTED]'
    $relevant = @($text -split '\r?\n' | Where-Object {
        $_ -match '(?i)error|failed|exit code|COMPOSER_|PHP_REQUIRED_EXTENSION|npm ERR|could not|requires ext-|not found|manifest|checksum|^#\d+\s+\[|^\s*>\s*\[|^Dockerfile:|^\s*(?:>>>|\d+\s*\|)'
    })
    Write-Output 'BUILD_DIAGNOSTICS_BEGIN (redacted; last 40 relevant lines; max 24000 characters)'
    $remaining = 24000
    foreach ($line in ($relevant | Select-Object -Last 40)) {
        if ($remaining -le 0) { break }
        $safe = $line -replace '[\x00-\x08\x0B-\x1F\x7F]', ''
        $limit = [Math]::Min(600, $remaining)
        if ($safe.Length -gt $limit) { $safe = $safe.Substring(0, $limit-3) + '...' }
        Write-Output $safe
        $remaining -= $safe.Length
    }
    Write-Output "BUILD_DIAGNOSTICS_END (relevant_lines=$($relevant.Count); output_bounded=true)"
}

function Copy-SafeTree([string]$Source, [string]$Destination) {
    if (-not (Test-Path -LiteralPath $Source)) { return }
    $item = Get-Item -LiteralPath $Source -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { $script:skipped++; return }
    if ($item.Name -match '^\.env($|\.)|^auth\.json$|^\.npmrc$|\.(key|pem|log)$') { $script:skipped++; return }
    if ($item.PSIsContainer) {
        New-Item -ItemType Directory -Path $Destination -Force | Out-Null
        foreach ($child in Get-ChildItem -LiteralPath $Source -Force) {
            if ($child.Name -in @('vendor','node_modules','.git') -or
                ($item.Name -eq 'bootstrap' -and $child.Name -eq 'cache')) { $script:skipped++; continue }
            Copy-SafeTree $child.FullName (Join-Path $Destination $child.Name)
        }
    } else {
        New-Item -ItemType Directory -Path (Split-Path $Destination -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $Source -Destination $Destination
        $script:copied++
    }
}

function Assert-TestProfile([hashtable]$Profile) {
    if ($Profile.AppEnv -cne 'testing' -or $Profile.DbHost -cne 'mysql' -or
        $Profile.DbName -cnotmatch '^edaratek_test_[a-f0-9]{12}$' -or
        $Profile.CachePath -cne '/app/storage/framework/testing/config.php') {
        throw 'ISOLATION_PROFILE_REJECTED'
    }
}

function Remove-OwnedScratch([string]$Path) {
    $full = [IO.Path]::GetFullPath($Path)
    $tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([IO.Path]::DirectorySeparatorChar)
    if ([IO.Path]::GetDirectoryName($full) -ne $tempRoot -or
        [IO.Path]::GetFileName($full) -notmatch '^edaratek-test-[a-f0-9]{12}$' -or
        (((Get-Item -LiteralPath $full -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw 'SCRATCH_CLEANUP_PATH_REJECTED'
    }
    # Same-shell, literal, validated direct child of OS temporary directory only.
    Remove-Item -LiteralPath $full -Recurse -Force
}

if ($SelfTest) {
    $good = @{AppEnv='testing';DbHost='mysql';DbName='edaratek_test_0123456789ab';CachePath='/app/storage/framework/testing/config.php'}
    Assert-TestProfile $good
    $rejected = 0
    foreach ($bad in @(@{AppEnv='production'},@{DbHost='shared.invalid'},@{DbName='shared'},@{CachePath='/app/bootstrap/cache/config.php'})) {
        $profile = $good.Clone()
        foreach ($key in $bad.Keys) { $profile[$key] = $bad[$key] }
        try { Assert-TestProfile $profile } catch { $rejected++ }
    }
    if ($rejected -ne 4) { throw 'GUARD_SELF_TEST_FAILED' }
    Write-Output '{"powershell_profile_guard":"PASS","unsafe_profiles_rejected":4,"database_operations":0,"php_guard_executed":false}'
    return
}

if (-not $PrepareOnly) {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Write-Output 'TEST_RUNTIME_BLOCKED: Docker CLI unavailable; no Laravel/DB boot attempted.'
        exit 30
    }
    if ($env:DOCKER_HOST) { Write-Output 'TEST_RUNTIME_BLOCKED: Explicit DOCKER_HOST is not accepted.'; exit 34 }
    $contextName = & docker context show 2>$null
    $endpoint = & docker context inspect $contextName --format '{{.Endpoints.docker.Host}}' 2>$null
    if ($LASTEXITCODE -ne 0 -or
        ([string]$endpoint -notmatch '^npipe:////\./pipe/docker(?:DesktopLinuxEngine|_engine)$' -and
         [string]$endpoint -cne 'unix:///var/run/docker.sock')) {
        Write-Output 'TEST_RUNTIME_BLOCKED: Only verified local Docker endpoints accepted.'; exit 35
    }
    $serverOs = & docker version --format '{{.Server.Os}}' 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Output 'TEST_RUNTIME_BLOCKED: Docker engine unavailable.'; exit 31 }
    if ([string]$serverOs -cne 'linux') { Write-Output 'TEST_RUNTIME_BLOCKED: Linux engine required.'; exit 36 }
    $null = & docker compose version 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Output 'TEST_RUNTIME_BLOCKED: Compose unavailable.'; exit 32 }
    if ($ValidateOnly) { Write-Output 'CONTAINER_TOOLS_AVAILABLE_NOT_RUNTIME_VERIFIED'; return }
}

$runId = [guid]::NewGuid().ToString('N').Substring(0,12)
$scratch = Join-Path ([IO.Path]::GetTempPath()) "edaratek-test-$runId"
$project = "edaratek-test-$runId"
$dbName = "edaratek_test_$runId"
Assert-TestProfile @{AppEnv='testing';DbHost='mysql';DbName=$dbName;CachePath='/app/storage/framework/testing/config.php'}
$composeStarted = $false
$savedProcessEnvironment = @{}
foreach ($key in @('EDARATEK_TEST_DB_NAME','EDARATEK_TEST_DB_PASSWORD','EDARATEK_TEST_ROOT_PASSWORD','EDARATEK_TEST_APP_KEY')) {
    $savedProcessEnvironment[$key] = [Environment]::GetEnvironmentVariable($key,'Process')
}
try {
    New-Item -ItemType Directory -Path $scratch | Out-Null
    foreach ($dir in @('app','bootstrap','config','database','lang','resources','routes','tests','tools/testing')) {
        Copy-SafeTree (Join-Path $repoRoot $dir) (Join-Path $scratch $dir)
    }
    # Passive, tracked reference files only; .github workflows are hashed, never executed here.
    $phase8References = @('.github/workflows/deploy.yml','.github/workflows/mobile-backend-tests.yml','docs/mobile-api-v1.md','docs/notification-policy.md')
    foreach ($file in (@('.htaccess','artisan','composer.json','composer.lock','phpunit.xml','phpunit.mysql.xml','package.json','package-lock.json','vite.config.js','postcss.config.js','tailwind.config.js','jsconfig.json') + $phase8References)) {
        Copy-SafeTree (Join-Path $repoRoot $file) (Join-Path $scratch $file)
    }
    # Vite assets are generated ONLY by Docker's locked Node build stage.
    foreach ($asset in @('public/.htaccess','public/index.php','public/favicon.ico','public/robots.txt')) {
        Copy-SafeTree (Join-Path $repoRoot $asset) (Join-Path $scratch $asset)
    }
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot '.dockerignore') -Destination (Join-Path $scratch '.dockerignore')
    New-Item -ItemType Directory -Path (Join-Path $scratch 'bootstrap/cache') -Force | Out-Null
    $forbidden = @(Get-ChildItem -LiteralPath $scratch -Recurse -Force | Where-Object {
        $_.Name -match '^\.env($|\.)|^auth\.json$|^\.npmrc$|\.(key|pem|log)$' -or
        ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0
    })
    if ($forbidden.Count -ne 0 -or (Test-Path (Join-Path $scratch 'bootstrap/cache/config.php')) -or
        (Test-Path (Join-Path $scratch 'public/build'))) {
        throw 'FILTERED_CONTEXT_REJECTED'
    }
    if ($PrepareOnly) {
        foreach ($reference in $phase8References) {
            $originalReference = Join-Path $repoRoot $reference
            $copiedReference = Join-Path $scratch $reference
            if (-not (Test-Path -LiteralPath $copiedReference) -or
                (Get-FileHash -LiteralPath $originalReference -Algorithm SHA256).Hash -cne (Get-FileHash -LiteralPath $copiedReference -Algorithm SHA256).Hash) {
                throw 'PHASE8_REFERENCE_COPY_REJECTED'
            }
        }
        [pscustomobject]@{context='PASS';copied_files=$script:copied;excluded_entries=$script:skipped;
            phase8_reference_files_verified=$phase8References.Count;forbidden_entries=0;laravel_booted=$false;docker_started=$false;cleanup='task_owned_scratch_only'} | ConvertTo-Json -Compress
        return
    }
    # Transient process-only synthetic credentials; never print compose config/env.
    $env:EDARATEK_TEST_DB_NAME = $dbName
    $env:EDARATEK_TEST_DB_PASSWORD = [guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
    $env:EDARATEK_TEST_ROOT_PASSWORD = [guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
    $keyBytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($keyBytes); $rng.Dispose()
    $env:EDARATEK_TEST_APP_KEY = 'base64:'+ [Convert]::ToBase64String($keyBytes)
    $composeFile = Join-Path $scratch 'tools/testing/compose.yaml'
    $emptyEnv = Join-Path $scratch 'tools/testing/empty.env'
    [IO.File]::WriteAllText($emptyEnv,'')
    # Compose resolves build.context relative to its project directory. The
    # existing ../.. context must resolve from tools/testing, not scratch root.
    $composeDirectory = [IO.Path]::GetDirectoryName($composeFile)
    $resolvedContext = [IO.Path]::GetFullPath((Join-Path $composeDirectory '../..'))
    if ($resolvedContext -cne [IO.Path]::GetFullPath($scratch)) { throw 'BUILD_CONTEXT_OUTSIDE_FILTERED_SCRATCH' }
    $composeArgs = @('compose','--project-directory',$composeDirectory,'--env-file',$emptyEnv,'-p',$project,'-f',$composeFile)
    # --progress is a Compose global option, before the build subcommand.
    $buildLog = & docker @composeArgs --progress plain build --pull 2>&1
    $buildExit = $LASTEXITCODE
    if ($buildExit -ne 0) {
        Write-Output "TEST_IMAGE_BUILD_FAILED: docker_exit=$buildExit"
        Write-SafeBuildDiagnostics $buildLog
        exit 33
    }
    $composeStarted = $true
    $null = & docker @composeArgs up -d --wait mysql 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Output 'ISOLATED_DATABASE_START_FAILED'; exit 37 }
    $internal = & docker network inspect "${project}_isolated" --format '{{.Internal}}' 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]$internal -cne 'true') {
        Write-Output 'INTERNAL_NETWORK_REQUIRED'; exit 38
    }
    $output = & docker @composeArgs run --rm --no-deps -T tests 2>&1
    $runExit = $LASTEXITCODE
    $seenPlatform = $false
    $seenBaseline = $false
    # Never export arbitrary test/provider/DB diagnostics or raw JUnit failures.
    foreach ($line in $output) {
        $safeLine = [string]$line
        if ($safeLine -notmatch '^\{"kind":"(guard|platform|baseline|infrastructure)"') { continue }
        try {
            $record = $safeLine | ConvertFrom-Json -ErrorAction Stop
            if ($record.kind -in @('guard','platform','baseline','infrastructure')) {
                Write-Output ($record | ConvertTo-Json -Compress -Depth 12)
                if ($record.kind -eq 'platform') { $seenPlatform = $true }
                if ($record.kind -eq 'baseline') { $seenBaseline = $true }
            }
        } catch { Write-Output 'STRUCTURED_EVIDENCE_PARSE_FAILED'; $runExit = 39 }
    }
    if ($runExit -eq 0 -and (-not $seenPlatform -or -not $seenBaseline)) {
        Write-Output 'REQUIRED_STRUCTURED_EVIDENCE_MISSING'; $runExit = 39
    }
    Write-Output "ISOLATED_RUN_EXIT=$runExit"
    exit $runExit
} finally {
    if ($composeStarted) { $null = & docker @composeArgs down --volumes --remove-orphans 2>&1 }
    foreach ($key in @('EDARATEK_TEST_DB_NAME','EDARATEK_TEST_DB_PASSWORD','EDARATEK_TEST_ROOT_PASSWORD','EDARATEK_TEST_APP_KEY')) {
        [Environment]::SetEnvironmentVariable($key,$savedProcessEnvironment[$key],'Process')
    }
    if (Test-Path -LiteralPath $scratch) { Remove-OwnedScratch $scratch }
}
