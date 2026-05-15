export const defaultDataProvisioningLabels = Object.freeze({
    stages: 'المراحل',
    stage_terms: 'الفصول الدراسية للمراحل',
    stage_grades: 'الصفوف',
    classrooms: 'الشعب',
    academic_years: 'الأعوام الدراسية',
    terms: 'الترمات',
    holidays: 'العطلات',
    leave_types: 'أنواع الإجازات',
    subjects: 'المواد',
});

export const defaultDataProvisioningOrder = Object.freeze([
    'stages',
    'stage_terms',
    'stage_grades',
    'classrooms',
    'academic_years',
    'terms',
    'holidays',
    'leave_types',
    'subjects',
]);

export const defaultDataProvisioningCountItems = (counts = {}) =>
    defaultDataProvisioningOrder.map((key) => ({
        key,
        label: defaultDataProvisioningLabels[key] || key,
        count: Number(counts?.[key] || 0),
    }));

export const defaultDataProvisioningSummaryText = (counts = {}) => {
    const enabledItems = defaultDataProvisioningCountItems(counts).filter((item) => item.count > 0);

    if (enabledItems.length === 0) {
        return 'لا يوجد أي قسم مفعّل في القالب المطابق حاليًا.';
    }

    if (enabledItems.length === 1) {
        return `القالب المطابق الحالي يتضمن ${enabledItems[0].label} فقط (${enabledItems[0].count}).`;
    }

    return `القالب المطابق الحالي يتضمن: ${enabledItems
        .map((item) => `${item.label} (${item.count})`)
        .join('، ')}.`;
};
