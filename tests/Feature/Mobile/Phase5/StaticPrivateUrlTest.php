<?php

namespace Tests\Feature\Mobile\Phase5;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaticPrivateUrlTest extends TestCase
{
    public function test_http_layer_denies_sensitive_physical_copies_and_symlinks_on_both_aliases(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ticketing/attachments/proof.txt', 'synthetic-sensitive');
        Storage::disk('public')->put('schools/logos/logo.txt', 'synthetic-logo');
        foreach (['copy', 'symlink'] as $mode) {
            $root = sys_get_temp_dir().'/edaratek_phase5_'.bin2hex(random_bytes(10)); mkdir($root);
            $process = null;
            try {
                foreach (['storage', 'media-files'] as $alias) {
                    if ($mode === 'symlink') { $this->assertTrue(symlink(Storage::disk('public')->path(''), $root.'/'.$alias)); }
                    else { (new Filesystem)->copyDirectory(Storage::disk('public')->path(''), $root.'/'.$alias); }
                }
                $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
                $this->assertIsResource($socket); $address = stream_socket_get_name($socket, false); fclose($socket);
                $env = getenv(); $env['EDARATEK_STATIC_ROOT'] = $root; $env['EDARATEK_STATIC_PUBLIC_ROOT'] = Storage::disk('public')->path('');
                $process = proc_open([PHP_BINARY, '-S', $address, '-t', $root, base_path('tools/testing/private-static-router.php')],
                    [['file', '/dev/null', 'r'], ['file', $root.'/server.log', 'a'], ['file', $root.'/server.log', 'a']], $pipes, base_path(), $env);
                $this->assertIsResource($process);
                $ready = false;
                for ($i = 0; $i < 40; $i++) {
                    $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
                    if ($probe) { fclose($probe); $ready = true; break; } usleep(50000);
                }
                $this->assertTrue($ready);
                foreach (['storage', 'media-files'] as $alias) {
                    [$status, $body, $headers] = $this->request($address, '/'.$alias.'/ticketing/attachments/proof.txt');
                    $this->assertSame(404, $status); $this->assertStringNotContainsString('synthetic-sensitive', $body);
                    $this->assertStringContainsString('no-store', strtolower($headers));
                    [$status, $body] = $this->request($address, '/'.$alias.'/schools/logos/logo.txt');
                    $this->assertSame(200, $status); $this->assertSame('synthetic-logo', $body);
                }
            } finally {
                if (is_resource($process)) { proc_terminate($process); proc_close($process); }
                foreach (['storage', 'media-files'] as $alias) { if (is_link($root.'/'.$alias)) { unlink($root.'/'.$alias); } }
                if (dirname($root) === sys_get_temp_dir() && str_starts_with(basename($root), 'edaratek_phase5_')) { (new Filesystem)->deleteDirectory($root); }
            }
        }
    }

    private function request(string $address, string $path): array
    {
        $curl = curl_init('http://'.$address.$path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_TIMEOUT => 10]);
        $result = curl_exec($curl); $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $length = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE); curl_close($curl);
        $this->assertIsString($result);
        return [$code, substr($result, $length), substr($result, 0, $length)];
    }
}
