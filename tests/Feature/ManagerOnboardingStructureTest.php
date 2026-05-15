<?php

namespace Tests\Feature;

use Tests\TestCase;

class ManagerOnboardingStructureTest extends TestCase
{
    public function test_manager_onboarding_hides_creation_selectors_after_first_school(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Manager/Onboarding.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('ui-page-shell manager-onboarding-shell', $content);
        $this->assertStringContainsString('manager-onboarding-grid mt-5 grid grid-cols-1 gap-4 lg:grid-cols-4', $content);
        $this->assertStringContainsString('selectedCountryId', $content);
        $this->assertStringContainsString('selectedGovernorateId', $content);
        $this->assertStringContainsString('selectedEducationTypeId', $content);
        $this->assertStringContainsString('selectedTemplateKey', $content);
        $this->assertStringContainsString('selectedSchoolType', $content);
        $this->assertStringContainsString('selectedEducationStageIds', $content);
        $this->assertStringContainsString("route('manager.onboarding.templates')", $content);
        $this->assertStringContainsString("route('manager.onboarding.governorates')", $content);
        $this->assertStringContainsString('manager-onboarding-school-type-panel', $content);
        $this->assertStringContainsString('manager-onboarding-stage-panel', $content);
        $this->assertStringContainsString("name=\"school_type\"", $content);
        $this->assertStringContainsString("name=\"education_stage_ids[]\"", $content);
        $this->assertStringContainsString("name=\"governorate_id\"", $content);
        $this->assertStringContainsString('!hasCurrentSchool && selectedCountryId && availableEducationTypes.length === 0', $content);
        $this->assertStringContainsString('manager-onboarding-banner--warning', $content);
        $this->assertStringContainsString('manager-onboarding-banner--success', $content);
        $this->assertStringContainsString("name=\"country_id\"", $content);
        $this->assertStringContainsString("name=\"education_type_id\"", $content);
        $this->assertStringContainsString("name=\"template_key\"", $content);
    }

    public function test_manager_onboarding_keeps_edit_only_flow_for_current_school(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Manager/Onboarding.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('ref="schoolDetailsSectionRef"', $content);
        $this->assertStringContainsString('startEditingCurrentSchool', $content);
        $this->assertStringContainsString("route('manager.onboarding.schools.update', currentSchool.value.id)", $content);
        $this->assertStringContainsString('ui-form-shell manager-onboarding-form-section', $content);
        $this->assertStringContainsString('ui-card-soft manager-onboarding-school-card', $content);
        $this->assertStringContainsString('xl:grid-cols-[19rem_minmax(0,1fr)]', $content);
        $this->assertStringContainsString('currentSchoolSummary?.country', $content);
        $this->assertStringContainsString('currentSchoolSummary?.educationType', $content);
        $this->assertStringContainsString('currentSchoolSummary?.defaultTemplate', $content);
        $this->assertStringContainsString('currentSchoolSummary?.schoolType', $content);
        $this->assertStringContainsString('currentSchoolSummary?.educationStages?.length', $content);
        $this->assertStringContainsString('xl:grid-cols-8', $content);
    }

    public function test_manager_onboarding_uses_shared_dashboard_primitives_for_logo_upload_and_feedback(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Manager/Onboarding.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppInlineAlert from '@/Components/AppInlineAlert.vue';", $content);
        $this->assertStringContainsString('ui-page-hero manager-onboarding-hero', $content);
        $this->assertStringContainsString('ui-page-header', $content);
        $this->assertStringContainsString('ui-page-title', $content);
        $this->assertStringContainsString('ui-page-copy', $content);
        $this->assertStringContainsString('ui-card-soft manager-onboarding-preview-panel', $content);
        $this->assertStringContainsString('ui-card-soft manager-onboarding-upload-panel', $content);
        $this->assertStringContainsString('ui-card-soft manager-onboarding-upload-dropzone', $content);
        $this->assertStringContainsString('ui-primary-button manager-onboarding-primary-button', $content);
        $this->assertStringContainsString('<AppInlineAlert', $content);
        $this->assertStringContainsString('manager-onboarding-feedback manager-onboarding-feedback--success', $content);
        $this->assertStringContainsString('manager-onboarding-feedback manager-onboarding-feedback--error', $content);
        $this->assertStringContainsString('const ALLOWED_LOGO_TYPES', $content);
        $this->assertStringContainsString('resolvedSchoolLogoUrl', $content);
        $this->assertStringContainsString('handleLogoChange', $content);
        $this->assertStringContainsString(':global(html.theme-light) .manager-onboarding-hero', $content);
    }
}
