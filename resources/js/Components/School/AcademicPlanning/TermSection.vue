<script setup>
import AcademicPlanningSection from '@/Components/School/AcademicPlanning/AcademicPlanningSection.vue';

const props = defineProps({
    sectionId: {
        type: String,
        default: 'term-section',
    },
    isReady: {
        type: Boolean,
        default: true,
    },
    prerequisiteMessage: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <AcademicPlanningSection
        :section-id="props.sectionId"
        :step="2"
        title="Term Management"
        guidance="Choose an academic year and create terms with valid date ranges."
        :is-ready="props.isReady"
        :prerequisite-message="props.prerequisiteMessage"
    >
        <template #actions>
            <slot name="actions" />
        </template>
        <slot />
    </AcademicPlanningSection>
</template>
