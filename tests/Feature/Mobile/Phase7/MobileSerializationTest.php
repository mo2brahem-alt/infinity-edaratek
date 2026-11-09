<?php

namespace Tests\Feature\Mobile\Phase7;

use App\Models\{Subtask, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MobileContractFixtures;
use Tests\TestCase;

class MobileSerializationTest extends TestCase
{
    use RefreshDatabase, MobileContractFixtures;

    public static function dates(): array
    {
        return [['America/New_York','2026-03-08'],['America/New_York','2026-11-01'],['Asia/Riyadh','2026-11-09'],['UTC',null]];
    }

    #[DataProvider('dates')]
    public function test_date_only_survives_timezone_dst_arabic_and_null_with_stable_enums(string $zone,?string $date): void
    {
        $previous=date_default_timezone_get(); date_default_timezone_set($zone);
        try {
        config(['app.timezone'=>$zone]); $s=$this->schools();
        $statuses=[Subtask::STATUS_OPEN,Subtask::STATUS_IN_PROGRESS,Subtask::STATUS_SUBMITTED,Subtask::STATUS_APPROVED];
        foreach($statuses as $status){$this->task($s['A'],attributes:['title'=>'مهمة عربية','status'=>$status,'due_date'=>$date]);}
        $this->bearer($s['A']['staff']); $r=$this->getJson('/api/v1/staff/tasks?sort=id')->assertOk();
        $this->assertSame($statuses,$r->json('data.*.status'));
        foreach($r->json('data')as$item){$this->assertSame($date,$item['due_date']);$this->assertSame('مهمة عربية',$item['title']);$this->assertIsInt($item['id']);}
        } finally { date_default_timezone_set($previous); }
    }

    public function test_instants_are_utc_strings_and_large_persisted_ids_remain_json_integers(): void
    {
        $s=$this->schools();$task=$this->task($s['A']);$task->update(['id'=>2147483650]);$this->bearer($s['A']['staff']);
        $r=$this->getJson('/api/v1/staff/tasks/2147483650')->assertOk();$this->assertSame(2147483650,$r->json('data.id'));
        $devices=$this->getJson('/api/v1/auth/devices')->assertOk()->json('data');
        foreach($devices as$d){foreach(['created_at','expires_at','last_used_at']as$field){if($d[$field]!==null){$this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d+)?Z$/',$d[$field]);}}}
        // The current DTO has no monetary fields; do not invent a billing DTO to test it.
        $r->assertJsonMissingPath('data.amount')->assertJsonMissingPath('data.currency');
    }

    public function test_large_synthetic_lists_keep_output_and_query_growth_bounded(): void
    {
        $s=$this->schools(); $ticket=Ticket::create(['school_id'=>$s['A']['school']->id,'created_by'=>$s['A']['supervisor']->id,'assigned_to'=>$s['A']['manager']->id,'title'=>'Synthetic parent','description'=>'Synthetic description','status'=>Ticket::STATUS_OPEN]);
        $rows=[]; for($i=0;$i<10;$i++){$rows[]=['ticket_id'=>$ticket->id,'school_id'=>$s['A']['school']->id,'created_by'=>$s['A']['manager']->id,'assigned_to'=>$s['A']['staff']->id,'title'=>'Synthetic '.$i,'status'=>Subtask::STATUS_OPEN];}
        Subtask::insert($rows);$this->bearer($s['A']['staff']);$this->getJson('/api/v1/staff/tasks')->assertOk();
        $profile=function(int$count) {DB::enableQueryLog();DB::flushQueryLog();$start=microtime(true);$r=$this->getJson('/api/v1/staff/tasks?per_page=100')->assertOk();$elapsed=(microtime(true)-$start)*1000;$queries=count(DB::getQueryLog());DB::disableQueryLog();$r->assertJsonPath('meta.total',$count);return[$queries,$elapsed,$r];};
        [$small,$smallMs]=$profile(10);
        $rows=[];for($i=10;$i<1001;$i++){$rows[]=['ticket_id'=>$ticket->id,'school_id'=>$s['A']['school']->id,'created_by'=>$s['A']['manager']->id,'assigned_to'=>$s['A']['staff']->id,'title'=>'Synthetic '.$i,'status'=>Subtask::STATUS_OPEN];}
        foreach(array_chunk($rows,250)as$chunk){Subtask::insert($chunk);}
        [$large,$largeMs,$r]=$profile(1001);$r->assertJsonCount(100,'data');$this->assertLessThanOrEqual($small+2,$large,'Query count must not scale with rows');
        $this->assertLessThan(50000,strlen($r->getContent()));
        file_put_contents('/tmp/phase7-query-budget.json',json_encode(['small_rows'=>10,'large_rows'=>1001,'small_queries'=>$small,'large_queries'=>$large,'query_budget'=>$small+2,'small_ms'=>round($smallMs,3),'large_ms'=>round($largeMs,3),'returned_rows'=>100,'output_bytes'=>strlen($r->getContent()),'status'=>'PASS'],JSON_THROW_ON_ERROR));
    }
}
