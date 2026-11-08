<?php

namespace Tests\Feature\Mobile\Phase4;

use App\Models\Subtask;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MobileSchoolFixtures;
use Tests\TestCase;

class StaffTaskIsolationTest extends TestCase
{
    use RefreshDatabase, MobileSchoolFixtures;

    public function test_native_list_detail_and_counts_share_assignment_school_and_parent_scope(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A'], attributes: ['title' => 'Own matching']);
        $foreign = $this->task($s['B'], $s['A']['staff'], ['title' => 'Foreign matching']);
        $other = User::factory()->create(['role' => 'staff', 'school_id' => $s['A']['school']->id]);
        $colleague = $this->task($s['A'], $other, ['title' => 'Colleague matching']);
        $broken = $this->task($s['B'], $s['A']['staff'], ['school_id' => $s['A']['school']->id, 'title' => 'Broken matching']);
        TicketMessage::create(['subtask_id' => $own->id, 'user_id' => $s['A']['staff']->id, 'message' => 'Private transcript', 'message_type' => 'reply']);
        $this->withToken($this->nativeToken($s['A']['staff']))->getJson('/api/v1/staff/tasks?search=matching&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonMissingPath('data.0.messages');
        $this->getJson('/api/v1/staff/tasks/'.$own->id)->assertOk()->assertJsonPath('data.id', $own->id);
        foreach ([$foreign, $broken, $colleague] as $task) {
            $this->getJson('/api/v1/staff/tasks/'.$task->id)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        }
    }

    public function test_transferred_user_cannot_list_show_reply_or_submit_historical_tasks(): void
    {
        $s = $this->schools();
        $old = $this->task($s['A']);
        $staff = $s['A']['staff'];
        $token = $this->nativeToken($staff);
        $staff->update(['school_id' => $s['B']['school']->id]);
        $current = $this->task($s['B'], $staff);
        $this->withToken($token)->getJson('/api/v1/staff/tasks')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $current->id);
        $this->getJson('/api/v1/staff/tasks/'.$old->id)->assertNotFound();
        $this->actingAs($staff)->postJson(route('staff.subtasks.reply', $old), ['message' => 'Forbidden'])->assertForbidden();
        $this->postJson(route('staff.subtasks.submit', $old))->assertForbidden();
        $this->assertSame(Subtask::STATUS_OPEN, $old->fresh()->status);
        $this->assertDatabaseMissing('ticket_messages', ['subtask_id' => $old->id]);
    }

    public function test_web_workflow_keeps_its_shapes_but_excludes_foreign_parent_records(): void
    {
        $s = $this->schools();
        $own = $this->task($s['A']);
        $broken = $this->task($s['B'], $s['A']['staff'], ['school_id' => $s['A']['school']->id]);
        $this->actingAs($s['A']['staff'])->getJson(route('staff.subtasks.index', ['per_page' => 1]))
            ->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $own->id);
        $this->getJson(route('staff.subtasks.show', $broken))->assertForbidden();
        $this->postJson(route('staff.subtasks.reply', $broken), ['message' => 'Denied'])->assertForbidden();
        $this->postJson(route('staff.subtasks.submit', $broken))->assertForbidden();
        $this->postJson(route('staff.subtasks.reply', $own), ['message' => 'Allowed'])->assertOk();
        $this->postJson(route('staff.subtasks.submit', $own))->assertOk()->assertJsonPath('status', Subtask::STATUS_SUBMITTED);
    }

    public function test_filters_pagination_and_selectors_cannot_broaden_scope(): void
    {
        $s = $this->schools();
        for ($i = 0; $i < 27; $i++) { $this->task($s['A']); }
        $this->task($s['B'], $s['A']['staff']);
        $this->withToken($this->nativeToken($s['A']['staff']))->getJson('/api/v1/staff/tasks')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 27);
        $this->getJson('/api/v1/staff/tasks?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 27);
        $this->getJson('/api/v1/staff/tasks?page=99')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.from', null);
        foreach (['per_page=101', 'per_page=0', 'page=-1', 'sort=school_id', 'status=UNKNOWN', 'school_id='.$s['B']['school']->id,
            'assigned_to='.$s['B']['staff']->id, 'department_id=999', 'tenant_id=999', 'organization_id=999', 'role=super_admin'] as $query) {
            $this->getJson('/api/v1/staff/tasks?'.$query)->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_narrowing_filters_literal_search_and_sort_keep_counts_private(): void
    {
        $s = $this->schools();
        $early = $this->task($s['A'], attributes: ['title' => 'Literal 100%_done', 'due_date' => '2026-11-01']);
        $late = $this->task($s['A'], attributes: ['title' => 'Other', 'due_date' => '2026-12-01', 'status' => Subtask::STATUS_SUBMITTED]);
        $this->task($s['B'], $s['A']['staff'], ['title' => 'Literal 100%_done', 'due_date' => '2026-01-01']);
        $this->withToken($this->nativeToken($s['A']['staff']))->getJson('/api/v1/staff/tasks?sort=due_date&per_page=100')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $early->id)->assertJsonPath('data.1.id', $late->id);
        $this->getJson('/api/v1/staff/tasks?status=SUBMITTED&sort=-due_date')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $late->id);
        $this->getJson('/api/v1/staff/tasks?search='.rawurlencode('100%_'))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $early->id);
        foreach (['0', '-1', 'invalid', '999999'] as $id) {
            $this->getJson('/api/v1/staff/tasks/'.$id)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        }
    }

    public function test_native_tasks_require_bearer_even_with_valid_web_session(): void
    {
        $s = $this->schools();
        $this->actingAs($s['A']['staff'])->getJson('/api/v1/staff/tasks')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_dashboard_uses_current_owned_school_and_excludes_inconsistent_parent_counts(): void
    {
        $s = $this->schools();
        foreach (['A', 'B'] as $key) {
            $s[$key]['staff']->update(['school_staff_type' => User::SCHOOL_STAFF_EDUCATIONAL]);
            $this->task($s[$key]);
        }
        $this->task($s['A'], $s['B']['staff'], ['school_id' => $s['B']['school']->id]);
        // Two schools remain owned by the same manager; current context must win over hasOne order.
        $s['B']['school']->update(['manager_user_id' => $s['A']['manager']->id]);
        $s['A']['manager']->update(['school_id' => $s['B']['school']->id]);
        $this->actingAs($s['A']['manager'])->get(route('manager.dashboard'))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('analytics.school.id', $s['B']['school']->id)
                ->has('analytics.teachers.teachersOpenTickets', 1)
                ->where('analytics.teachers.teachersOpenTickets.0.value', 1));
    }
}
