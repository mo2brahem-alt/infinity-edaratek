<?php

namespace Tests\Feature\Mobile\Phase7;

use App\Models\Subtask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MobileContractFixtures;
use Tests\TestCase;

class MobilePaginationTest extends TestCase
{
    use RefreshDatabase, MobileContractFixtures;

    public static function invalidQueries(): array
    {
        return array_map(static fn ($q) => [$q], ['page=0','page=-1','page=1.5','page=abc','page=','page[]=1','page=9223372036854775808',
            'per_page=0','per_page=-1','per_page=101','per_page=abc','per_page=1.5','per_page=','per_page[]=2','school_id=1','token=ignored','direction=asc']);
    }

    #[DataProvider('invalidQueries')]
    public function test_device_and_task_lists_and_attachment_pages_reject_invalid_or_unsupported_input(string $query): void
    {
        $s = $this->schools(); $task = $this->task($s['A']); $this->bearer($s['A']['staff']);
        foreach (['/auth/devices', '/staff/tasks', '/staff/tasks/'.$task->id] as $path) {
            $this->assertSafeError($this->getJson('/api/v1'.$path.'?'.$query), 422, 'VALIDATION_FAILED');
        }
    }

    public function test_default_explicit_empty_and_overrun_envelopes_have_stable_relative_links_and_counts(): void
    {
        $s = $this->schools(); $this->bearer($s['A']['staff']);
        foreach (['/staff/tasks', '/auth/devices'] as $path) {
            $a = $this->getJson('/api/v1'.$path)->assertOk(); $b = $this->getJson('/api/v1'.$path.'?per_page=25')->assertOk();
            $this->exactKeys(['data','meta','links'], $a->json()); $this->assertSame($a->json('meta'), $b->json('meta'));
            $this->exactKeys(['current_page','per_page','total','last_page','from','to'], $a->json('meta'));
            $this->assertIsArray($a->json('data')); $this->assertSame(25, $a->json('meta.per_page'));
            $r = $this->getJson('/api/v1'.$path.'?page=3&per_page=100')->assertOk()->assertJsonCount(0,'data')
                ->assertJsonPath('meta.current_page',3)->assertJsonPath('meta.from',null)->assertJsonPath('meta.to',null);
            foreach ($r->json('links') as $url) { if ($url !== null) { $this->assertStringStartsWith('/api/v1'.$path.'?', $url); $this->assertStringNotContainsString('token', $url); } }
            $this->assertNull($r->json('links.next')); $this->assertNotNull($r->json('links.prev'));
            $this->getJson('/api/v1'.$path.'?page=2147483647&per_page=100')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.current_page',2147483647);
        }
    }

    public function test_title_literal_search_status_sorts_ties_nulls_and_foreign_counts(): void
    {
        $s = $this->schools();
        $a = $this->task($s['A'], attributes: ['title'=>'100%_done', 'due_date'=>'2026-11-01']);
        $b = $this->task($s['A'], attributes: ['title'=>'100xxdone', 'due_date'=>'2026-11-01', 'status'=>Subtask::STATUS_SUBMITTED]);
        $null = $this->task($s['A'], attributes: ['title'=>'Other', 'due_date'=>null, 'description'=>'100%_done']);
        $this->task($s['B'], $s['A']['staff'], ['title'=>'100%_done']);
        $this->bearer($s['A']['staff']);
        $r = $this->getJson('/api/v1/staff/tasks?'.http_build_query(['search'=>'%_']))->assertOk()->assertJsonPath('meta.total',1)->assertJsonPath('data.0.id',$a->id);
        $this->assertStringContainsString('search=', $r->json('links.first'));
        $this->getJson('/api/v1/staff/tasks?status=SUBMITTED')->assertOk()->assertJsonPath('meta.total',1)->assertJsonPath('data.0.id',$b->id);
        foreach (['due_date','-due_date'] as $sort) { $this->assertSame([$b->id,$a->id,$null->id], $this->getJson('/api/v1/staff/tasks?sort='.$sort)->assertOk()->json('data.*.id')); }
        $this->assertSame([$a->id,$b->id,$null->id], $this->getJson('/api/v1/staff/tasks?sort=id')->json('data.*.id'));
        foreach (['status=INVALID','sort=title','sort=id%20desc','search[]=bad','search='.str_repeat('a',101)] as $query) {
            $this->getJson('/api/v1/staff/tasks?'.$query)->assertUnprocessable()->assertJsonPath('code','VALIDATION_FAILED');
        }
    }

    public function test_detail_attachments_are_bounded_with_consistent_authorized_metadata_count(): void
    {
        Storage::fake('school_attachments'); $s = $this->schools(); $task = $this->task($s['A']);
        for ($i=0; $i<27; $i++) { $this->contractFile($s['A'], $task); }
        $this->contractFile($s['A'], $task, ['school_id'=>$s['B']['school']->id]);
        $this->contractFile($s['A'], $task, ['module'=>'student_records']);
        $this->contractFile($s['A'], $task, ['attachable_id'=>999999]);
        $this->bearer($s['A']['staff']);
        $this->getJson('/api/v1/staff/tasks/'.$task->id)->assertOk()->assertJsonCount(25,'data.attachments')->assertJsonPath('data.attachments_meta.total',27);
        $this->getJson('/api/v1/staff/tasks/'.$task->id.'?page=2')->assertOk()->assertJsonCount(2,'data.attachments')->assertJsonPath('data.attachments_meta.total',27);
        $this->getJson('/api/v1/staff/tasks/'.$task->id.'?page=99')->assertOk()->assertJsonCount(0,'data.attachments')->assertJsonPath('data.attachments_meta.from',null);
    }
}
