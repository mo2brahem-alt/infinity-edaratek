<?php

namespace Tests\Feature\Mobile\Phase8;

use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\Support\MobileContractFixtures;
use Tests\TestCase;

class LargePreparedListsTest extends TestCase
{
    use RefreshDatabase, MobileContractFixtures;

    private function measuredGet(string $url, int $total, string $rows, string $meta): array
    {
        DB::enableQueryLog(); DB::flushQueryLog(); $start=microtime(true);
        try {
            $response=$this->getJson($url)->assertOk()->assertJsonPath($meta.'.total',$total);
            $count=count(DB::getQueryLog()); $ms=(microtime(true)-$start)*1000;
        } finally { DB::disableQueryLog(); }
        return [$response,['rows'=>$total,'queries'=>$count,'ms'=>round($ms,3),'returned_rows'=>count($response->json($rows)), 'output_bytes'=>strlen($response->getContent())]];
    }

    private function recordProfile(string $name, array $profile): void
    {
        $path='/tmp/phase8-large-lists.json';
        $profiles=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
        $profiles[$name]=$profile;
        file_put_contents($path,json_encode($profiles,JSON_THROW_ON_ERROR));
    }

    public function test_device_list_over_one_hundred_preserves_owner_expiry_bounds_and_query_budget(): void
    {
        $a=User::factory()->create();$b=User::factory()->create();$this->bearer($a);$tokens=app(NativeTokenService::class);
        for($i=1;$i<3;$i++){$tokens->issue($a,(string)Str::uuid(),'Synthetic device '.$i);}
        $a->createToken('native:'.Str::uuid().':Expired',['native:access'],now()->subDay());
        $a->createToken('Integration fixture',['*'],now()->addDay());
        for($i=0;$i<5;$i++){$tokens->issue($b,(string)Str::uuid(),'FOREIGN_DEVICE_SENTINEL');}
        $this->getJson('/api/v1/auth/devices')->assertOk()->assertJsonPath('meta.total',3);
        [, $small]=$this->measuredGet('/api/v1/auth/devices?per_page=100',3,'data','meta');
        for($i=3;$i<137;$i++){$tokens->issue($a,(string)Str::uuid(),'Synthetic device '.$i);}
        [$response,$large]=$this->measuredGet('/api/v1/auth/devices?per_page=100',137,'data','meta');
        $response->assertJsonCount(100,'data');$this->assertLessThanOrEqual($small['queries']+2,$large['queries']);
        $this->assertStringNotContainsString('FOREIGN_DEVICE_SENTINEL',$response->getContent());
        $this->assertStringNotContainsString('Integration fixture',$response->getContent());
        $this->assertStringNotContainsString('Expired',$response->getContent());
        foreach($response->json('data')as$item){$this->exactKeys(['id','device_name','created_at','last_used_at','expires_at','is_current'],$item);}
        $this->getJson('/api/v1/auth/devices')->assertOk()->assertJsonCount(25,'data')->assertJsonPath('meta.total',137);
        $this->getJson('/api/v1/auth/devices?page=2&per_page=100')->assertOk()->assertJsonCount(37,'data')->assertJsonPath('meta.total',137);
        $this->getJson('/api/v1/auth/devices?page=3&per_page=100')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.from',null);
        $this->recordProfile('devices',['small'=>$small,'large'=>$large,'query_budget'=>$small['queries']+2,'status'=>'PASS']);
    }

    public function test_attachment_metadata_over_one_hundred_and_many_histories_is_bounded_before_serialization(): void
    {
        Storage::fake('school_attachments');$s=$this->schools();$task=$this->task($s['A']);
        for($i=0;$i<3;$i++){$this->contractFile($s['A'],$task);}
        $this->bearer($s['A']['staff']);$path='/api/v1/staff/tasks/'.$task->id;
        $this->getJson($path)->assertOk()->assertJsonPath('data.attachments_meta.total',3);
        [, $small]=$this->measuredGet($path.'?per_page=100',3,'data.attachments','data.attachments_meta');
        for($i=3;$i<139;$i++){$this->contractFile($s['A'],$task);}
        $this->contractFile($s['A'],$task,['school_id'=>$s['B']['school']->id,'file_name'=>'FOREIGN_METADATA_SENTINEL']);
        $this->contractFile($s['A'],$task,['attachable_id'=>999999999,'file_name'=>'BROKEN_METADATA_SENTINEL']);
        $foreign=$this->contractFile($s['B']);
        [$response,$large]=$this->measuredGet($path.'?per_page=100',139,'data.attachments','data.attachments_meta');
        $response->assertJsonCount(100,'data.attachments');$this->assertLessThanOrEqual($small['queries']+2,$large['queries']);
        foreach(['FOREIGN_METADATA_SENTINEL','BROKEN_METADATA_SENTINEL','Hidden transcript']as$text){$this->assertStringNotContainsString($text,$response->getContent());}
        foreach($response->json('data.attachments')as$item){$this->exactKeys(['id','name','mime_type','size_bytes','download_url'],$item);}
        $this->getJson($path)->assertOk()->assertJsonCount(25,'data.attachments')->assertJsonPath('data.attachments_meta.total',139);
        $r=$this->getJson($path.'?page=2&per_page=100')->assertOk()->assertJsonCount(39,'data.attachments')->assertJsonPath('data.attachments_meta.total',139);
        $this->assertStringStartsWith($path.'?', $r->json('data.attachments_links.first'));
        $this->getJson($path.'?page=3&per_page=100')->assertOk()->assertJsonCount(0,'data.attachments')->assertJsonPath('data.attachments_meta.from',null);
        $this->assertSafeError($this->getJson('/api/v1/staff/tasks/'.$foreign->message->subtask_id),404,'NOT_FOUND');
        $this->recordProfile('task_attachments',['small'=>$small,'large'=>$large,'query_budget'=>$small['queries']+2,'status'=>'PASS']);
    }
}
