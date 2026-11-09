<?php

namespace Tests\Feature\Mobile\Phase7;

use App\Models\User;
use App\Services\Mobile\NativeRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, File};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MobileContractFixtures;
use Tests\TestCase;

class MobileRatePolicyTest extends TestCase
{
    use RefreshDatabase, MobileContractFixtures;

    public function test_identity_limit_covers_unknown_credentials_and_ip_rotation_and_recovers(): void
    {
        $user = User::factory()->create();
        for ($i=1; $i<=5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.'.$i])->postJson('/api/v1/auth/login', $this->credentials($user, ['password'=>'wrong']))->assertUnauthorized();
        }
        $r = $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.99'])->postJson('/api/v1/auth/login', $this->credentials($user));
        $this->assertSafeError($r,429,'RATE_LIMITED'); $this->assertSame((int) $r->headers->get('Retry-After'), $r->json('retry_after'));
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/login', $this->credentials($user))->assertOk(); $this->travelBack();
    }

    public function test_login_ip_limit_covers_rotating_unknown_accounts(): void
    {
        $user = User::factory()->create();
        for ($i=0; $i<30; $i++) { $this->postJson('/api/v1/auth/login', $this->credentials($user, ['email'=>'unknown-'.$i.'@example.test']))->assertUnauthorized(); }
        $this->assertSafeError($this->postJson('/api/v1/auth/login', $this->credentials($user)),429,'RATE_LIMITED');
    }

    public function test_account_budget_survives_identifier_changes_and_rotating_ips_before_credentials(): void
    {
        $user=User::factory()->create();
        for($i=1;$i<=5;$i++){
            $user->update(['email'=>'synthetic-account-'.$i.'@example.test']);
            $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.'.$i])->postJson('/api/v1/auth/login',$this->credentials($user,['password'=>'wrong']))->assertUnauthorized();
        }
        $user->update(['email'=>'synthetic-account-final@example.test']);
        $r=$this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.99'])->postJson('/api/v1/auth/login',$this->credentials($user));
        $this->assertSafeError($r,429,'RATE_LIMITED');$this->assertDatabaseCount('personal_access_tokens',0);
        $this->travel(61)->seconds();$this->postJson('/api/v1/auth/login',$this->credentials($user))->assertOk();$this->travelBack();
    }

    public function test_read_and_session_http_boundaries_and_cooldown_are_actual_configured_values(): void
    {
        $user = User::factory()->create(); $this->bearer($user);
        $this->assertSame(120,config('mobile.native_read_attempts_per_minute')); $this->assertSame(10,config('mobile.native_session_attempts_per_minute'));
        for ($i=0; $i<120; $i++) { $this->getJson('/api/v1/me')->assertOk(); }
        $this->assertSafeError($this->getJson('/api/v1/me'),429,'RATE_LIMITED');
        for ($i=0; $i<10; $i++) { $this->deleteJson('/api/v1/auth/devices/999999999')->assertNotFound(); }
        $this->assertSafeError($this->deleteJson('/api/v1/auth/devices/999999999'),429,'RATE_LIMITED');
        $this->travel(61)->seconds(); $this->getJson('/api/v1/me')->assertOk();
        $this->deleteJson('/api/v1/auth/devices/999999999')->assertNotFound(); $this->travelBack();
    }

    public function test_shared_read_ip_budget_covers_multiple_accounts_without_exposing_keys(): void
    {
        $limiter=app(NativeRateLimiter::class); $request=Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR'=>'192.0.2.77']);
        $this->assertSame(300,config('mobile.native_read_ip_attempts_per_minute'));
        for ($account=1;$account<=3;$account++) { $user=(new User)->forceFill(['id'=>$account]); for ($i=0;$i<100;$i++) { $this->assertNull($limiter->consumeRequest($request,$user,'read')); } }
        $this->assertGreaterThan(0,$limiter->consumeRequest($request,(new User)->forceFill(['id'=>4]),'read'));
        $this->assertSame(30,config('mobile.native_session_ip_attempts_per_minute'));
        for ($account=1;$account<=3;$account++) { $user=(new User)->forceFill(['id'=>$account]); for ($i=0;$i<10;$i++) { $this->assertNull($limiter->consumeRequest($request,$user,'session')); } }
        $this->assertGreaterThan(0,$limiter->consumeRequest($request,(new User)->forceFill(['id'=>4]),'session'));
    }

    public function test_cache_failure_is_generic_error_and_cannot_create_a_token(): void
    {
        $user=User::factory()->create(); Cache::shouldReceive('store')->once()->andThrow(new \RuntimeException('private-cache-sentinel'));
        $r=$this->postJson('/api/v1/auth/login',$this->credentials($user)); $this->assertSafeError($r,500,'INTERNAL_ERROR');
        $this->assertStringNotContainsString('private-cache-sentinel',$r->getContent()); $this->assertDatabaseCount('personal_access_tokens',0);
    }

    public static function budgetKinds(): array { return [['login',5],['read',120],['session',10],['reset',3]]; }

    public function test_lock_timeout_denies_admission_without_token_or_unlocked_fallback(): void
    {
        $user=User::factory()->create();
        $cache=\Mockery::mock(\Illuminate\Cache\Repository::class);
        $lock=\Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('block')->once()->with(2)->andThrow(new \Illuminate\Contracts\Cache\LockTimeoutException);
        $cache->shouldReceive('lock')->once()->andReturn($lock); Cache::shouldReceive('store')->once()->andReturn($cache);
        $r=$this->postJson('/api/v1/auth/login',$this->credentials($user));
        $this->assertSafeError($r,429,'RATE_LIMITED');$r->assertHeader('Retry-After','1')->assertJsonPath('retry_after',1);
        $this->assertDatabaseCount('personal_access_tokens',0);
    }

    #[DataProvider('budgetKinds')]
    public function test_independent_processes_cannot_overbook_a_shared_budget(string $kind,int $maximum): void
    {
        $root=storage_path('framework/testing/p7-rate-'.Str::uuid()); File::makeDirectory($root,0755,true);
        $this->assertSame(realpath(storage_path('framework/testing')),realpath(dirname($root)));
        config(['cache.limiter'=>'phase7_rate','cache.stores.phase7_rate'=>['driver'=>'file','path'=>$root,'lock_path'=>$root]]);
        $limiter=app(NativeRateLimiter::class); $request=Request::create('/','POST',['email'=>'synthetic-limit@example.test'],[],[],['REMOTE_ADDR'=>'192.0.2.100']);
        $actor=(new User)->forceFill(['id'=>123]);
        $consume=fn()=>match($kind){'login'=>$limiter->consumeLogin($request,'synthetic-limit@example.test',$actor),'reset'=>$limiter->consumeReset($request),default=>$limiter->consumeRequest($request,$actor,$kind)};
        for($i=0;$i<$maximum-2;$i++){ $this->assertNull($consume()); }
        $processes=[]; $pipes=[];
        try {
            for($i=1;$i<=6;$i++){
                $processes[$i]=proc_open([PHP_BINARY,base_path('tools/testing/native-rate-worker.php'),'boot',$kind,$root,(string)$i], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes[$i],base_path());
                $this->assertIsResource($processes[$i]); fclose($pipes[$i][0]);
            }
            $deadline=microtime(true)+15;
            while(count(glob($root.'/ready-*'))!==6&&microtime(true)<$deadline){usleep(10000);}
            $this->assertCount(6,glob($root.'/ready-*'),'All isolated workers must reach the shared start barrier'); File::put($root.'/start','start');
            $admitted=0; $denied=0;
            foreach($processes as $i=>$process){
                $out=stream_get_contents($pipes[$i][1]); stream_get_contents($pipes[$i][2]); fclose($pipes[$i][1]); fclose($pipes[$i][2]);
                $exit=proc_close($process); unset($processes[$i]); $this->assertContains($exit,[0,10]);
                $item=json_decode($out,true,512,JSON_THROW_ON_ERROR); $this->assertSame('phase7_atomic_budget',$item['fixture']);
                $item['admitted']?$admitted++:$denied++;
            }
            $this->assertSame(2,$admitted); $this->assertSame(4,$denied);
        } finally {
            foreach($processes as $process){if(is_resource($process)){proc_terminate($process);proc_close($process);}}
            config(['cache.limiter'=>null]);
            if(realpath(dirname($root))===realpath(storage_path('framework/testing'))&&preg_match('/^p7-rate-[a-f0-9-]{36}$/D',basename($root))){File::deleteDirectory($root);}
        }
    }
}
