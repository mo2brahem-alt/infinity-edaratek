<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class SchoolPermissionCatalog
{
    public const TYPE_ADMINISTRATIVE = 'administrative';
    public const TYPE_EDUCATIONAL = 'educational';

    /**
     * @return array<int, array{
     *     key: string,
     *     type: string,
     *     label: string,
     *     description: string,
     *     tone: string,
     *     permissions: array<int, array{
     *         name: string,
     *         label: string,
     *         description: string,
     *         tone: string,
     *         module: string,
     *         sensitive: bool,
     *         manager_assignable: bool,
     *         dependencies: array<int, string>,
     *         related_capability: string|null
     *     }>
     * }>
     */
    private static function catalog(): array
    {
        return [
            [
                'key' => 'students',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'الطلاب والهيكل المدرسي',
                'description' => 'تفويض إدارة هيكل الطلاب والصفوف والفصول وملفات الطلاب داخل المدرسة.',
                'tone' => 'cyan',
                'permissions' => [
                    [
                        'name' => 'school.student_structure.manage',
                        'label' => 'إدارة الطلاب والهيكل المدرسي',
                        'description' => 'يمنح الوصول إلى المراحل والصفوف والفصول وبيانات الطلاب داخل المدرسة.',
                        'tone' => 'cyan',
                        'module' => 'students',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_student_structure',
                    ],
                ],
            ],
            [
                'key' => 'attendance',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'الحضور والانصراف',
                'description' => 'تفويض تسجيل الحضور اليومي ومتابعة حالات الغياب والتأخر داخل المدرسة.',
                'tone' => 'blue',
                'permissions' => [
                    [
                        'name' => 'school.student_attendance.manage',
                        'label' => 'إدارة الحضور اليومي',
                        'description' => 'يسمح بتسجيل حضور الطلاب وتحديثه ومتابعة سجلاته اليومية.',
                        'tone' => 'blue',
                        'module' => 'attendance',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_student_attendance',
                    ],
                ],
            ],
            [
                'key' => 'leaves',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'الإجازات والأعذار',
                'description' => 'تفويض تشغيل طلبات الإجازات، أنواعها، والتقويم والعطل المرتبطة بها.',
                'tone' => 'violet',
                'permissions' => [
                    [
                        'name' => 'school.student_leaves.manage',
                        'label' => 'إدارة إجازات الطلاب',
                        'description' => 'يسمح بإنشاء طلبات الإجازات ومتابعتها واعتمادها داخل المدرسة.',
                        'tone' => 'violet',
                        'module' => 'leaves',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_student_leaves',
                    ],
                    [
                        'name' => 'school.leave_types.manage',
                        'label' => 'إدارة أنواع الإجازات',
                        'description' => 'يسمح بضبط أنواع الإجازات والسياسات التشغيلية المرتبطة بها.',
                        'tone' => 'amber',
                        'module' => 'leaves',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_leave_types',
                    ],
                    [
                        'name' => 'school.calendar.manage',
                        'label' => 'إدارة التقويم المدرسي',
                        'description' => 'يسمح بإدارة التقويم المدرسي والإعدادات الزمنية الأساسية للمدرسة.',
                        'tone' => 'indigo',
                        'module' => 'calendar',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_school_calendar',
                    ],
                    [
                        'name' => 'school.holidays.manage',
                        'label' => 'إدارة العطل المدرسية',
                        'description' => 'يسمح بإضافة العطل الرسمية والمدرسية وربطها بالتقويم المعتمد.',
                        'tone' => 'emerald',
                        'module' => 'calendar',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_school_holidays',
                    ],
                ],
            ],
            [
                'key' => 'planning',
                'type' => self::TYPE_EDUCATIONAL,
                'label' => 'التخطيط الأكاديمي والجداول',
                'description' => 'تفويض إدارة السنوات والترمات والمواد والجداول والتخطيط الأكاديمي.',
                'tone' => 'emerald',
                'permissions' => [
                    [
                        'name' => 'school.academic_planning.manage',
                        'label' => 'إدارة التخطيط الأكاديمي والجداول',
                        'description' => 'يسمح بإدارة الهيكل الأكاديمي والجداول والإسنادات التعليمية.',
                        'tone' => 'emerald',
                        'module' => 'academic_planning',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_academic_planning',
                    ],
                ],
            ],
            [
                'key' => 'exams',
                'type' => self::TYPE_EDUCATIONAL,
                'label' => 'الاختبارات',
                'description' => 'تفويض تشغيل الاختبارات وجدولتها وإدارتها داخل المدرسة.',
                'tone' => 'orange',
                'permissions' => [
                    [
                        'name' => 'school.exams.manage',
                        'label' => 'إدارة الاختبارات',
                        'description' => 'يسمح بفتح وحدة الاختبارات وإدارة إعداداتها وتشغيلها داخل المدرسة.',
                        'tone' => 'orange',
                        'module' => 'exams',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_school_exams',
                    ],
                ],
            ],
            [
                'key' => 'reports',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'التقارير',
                'description' => 'تفويض الوصول إلى تقارير المدرسة وتشغيل التصدير عند الحاجة.',
                'tone' => 'slate',
                'permissions' => [
                    [
                        'name' => 'school.reports.view',
                        'label' => 'عرض تقارير المدرسة',
                        'description' => 'يسمح بالوصول إلى شاشة التقارير المدرسية ومراجعة البيانات المجمعة.',
                        'tone' => 'slate',
                        'module' => 'reports',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => 'can_manage_school_reports',
                    ],
                    [
                        'name' => 'school.reports.export',
                        'label' => 'تصدير تقارير المدرسة',
                        'description' => 'يسمح بتصدير التقارير إلى CSV أو Excel أو JSON. صلاحية حساسة لأنها تنقل البيانات خارج النظام.',
                        'tone' => 'red',
                        'module' => 'reports',
                        'sensitive' => true,
                        'manager_assignable' => true,
                        'dependencies' => ['school.reports.view'],
                        'related_capability' => 'can_export_school_reports',
                    ],
                ],
            ],
            [
                'key' => 'certificates',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'الشهادات',
                'description' => 'تفويض إنشاء قوالب الشهادات وإصدار شهادات الطلاب وطباعتها داخل نطاق المدرسة.',
                'tone' => 'emerald',
                'permissions' => [
                    [
                        'name' => 'certificates.view',
                        'label' => 'عرض الشهادات',
                        'description' => 'يسمح بعرض الشهادات الصادرة وقوالب الشهادات داخل المدرسة.',
                        'tone' => 'emerald',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.templates.view',
                        'label' => 'عرض قوالب الشهادات',
                        'description' => 'يسمح بمراجعة قوالب الشهادات المتاحة داخل المدرسة.',
                        'tone' => 'emerald',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.templates.create',
                        'label' => 'إنشاء قوالب الشهادات',
                        'description' => 'يسمح بإنشاء قوالب شهادات جديدة داخل المدرسة.',
                        'tone' => 'cyan',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.templates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.templates.update',
                        'label' => 'تعديل قوالب الشهادات',
                        'description' => 'يسمح بتحديث قوالب الشهادات داخل المدرسة.',
                        'tone' => 'cyan',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.templates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.templates.delete',
                        'label' => 'حذف قوالب الشهادات',
                        'description' => 'صلاحية حساسة لتعطيل أو حذف قوالب شهادات داخل المدرسة.',
                        'tone' => 'red',
                        'module' => 'certificates',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => ['certificates.templates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.issue',
                        'label' => 'إصدار شهادة',
                        'description' => 'يسمح بإصدار شهادة لطالب داخل نفس المدرسة.',
                        'tone' => 'emerald',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.print',
                        'label' => 'طباعة أو تحميل شهادة',
                        'description' => 'يسمح بفتح شهادة صادرة للطباعة أو الحفظ كملف PDF.',
                        'tone' => 'blue',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.cancel',
                        'label' => 'إلغاء شهادة صادرة',
                        'description' => 'صلاحية حساسة لإلغاء شهادة سبق إصدارها.',
                        'tone' => 'red',
                        'module' => 'certificates',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.verify',
                        'label' => 'التحقق من شهادة',
                        'description' => 'يسمح بمراجعة بيانات التحقق من الشهادات داخل النظام.',
                        'tone' => 'slate',
                        'module' => 'certificates',
                        'sensitive' => false,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.signatures.manage',
                        'label' => 'إدارة التوقيعات والأختام',
                        'description' => 'صلاحية حساسة لإدارة توقيعات وأختام الشهادات داخل المدرسة.',
                        'tone' => 'red',
                        'module' => 'certificates',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.frames.manage',
                        'label' => 'إدارة إطارات الشهادات',
                        'description' => 'صلاحية محجوزة لإدارة مكتبة إطارات الشهادات.',
                        'tone' => 'amber',
                        'module' => 'certificates',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => ['certificates.view'],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'certificates.bulk_issue',
                        'label' => 'إصدار شهادات جماعية',
                        'description' => 'يسمح بإصدار شهادات لعدة طلاب داخل نفس المدرسة في عملية واحدة.',
                        'tone' => 'amber',
                        'module' => 'certificates',
                        'sensitive' => true,
                        'manager_assignable' => true,
                        'dependencies' => ['certificates.issue'],
                        'related_capability' => null,
                    ],
                ],
            ],
            [
                'key' => 'tickets',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'التذاكر والمهام',
                'description' => 'محجوزة للتوسعات القادمة في إدارة التذاكر والمهام المدرسية.',
                'tone' => 'purple',
                'permissions' => [
                    [
                        'name' => 'school.tickets.manage',
                        'label' => 'إدارة التذاكر المدرسية',
                        'description' => 'صلاحية مستقبلية لإدارة تذاكر المدرسة ضمن نفس النطاق.',
                        'tone' => 'purple',
                        'module' => 'tickets',
                        'sensitive' => false,
                        'manager_assignable' => false,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'school.subtasks.manage',
                        'label' => 'إدارة المهام الفرعية',
                        'description' => 'صلاحية مستقبلية لإدارة المهام الفرعية داخل المدرسة.',
                        'tone' => 'purple',
                        'module' => 'tickets',
                        'sensitive' => false,
                        'manager_assignable' => false,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                ],
            ],
            [
                'key' => 'attachments',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'المرفقات',
                'description' => 'محجوزة للتوسعات القادمة في تفويضات المرفقات العامة والحساسة.',
                'tone' => 'pink',
                'permissions' => [
                    [
                        'name' => 'school.attachments.manage',
                        'label' => 'إدارة المرفقات العامة',
                        'description' => 'صلاحية مستقبلية لإدارة المرفقات عبر وحدات المدرسة.',
                        'tone' => 'pink',
                        'module' => 'attachments',
                        'sensitive' => false,
                        'manager_assignable' => false,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'school.attachments.delete',
                        'label' => 'حذف المرفقات الحساسة',
                        'description' => 'صلاحية حساسة ومحجوزة للتوسعات القادمة في إدارة الحذف الدقيق للمرفقات.',
                        'tone' => 'red',
                        'module' => 'attachments',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => ['school.attachments.manage'],
                        'related_capability' => null,
                    ],
                ],
            ],
            [
                'key' => 'settings',
                'type' => self::TYPE_ADMINISTRATIVE,
                'label' => 'إعدادات المدرسة',
                'description' => 'صلاحيات حساسة تبقى محجوزة لمدير المدرسة أو للإدارة العليا حسب الحاجة.',
                'tone' => 'amber',
                'permissions' => [
                    [
                        'name' => 'school.users.manage',
                        'label' => 'إدارة مستخدمي المدرسة',
                        'description' => 'صلاحية حساسة لإدارة مستخدمي المدرسة وحساباتهم.',
                        'tone' => 'amber',
                        'module' => 'school_settings',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'school.delegations.manage',
                        'label' => 'إدارة التفويضات المدرسية',
                        'description' => 'صلاحية حساسة لإدارة التفويضات المدرسية نفسها.',
                        'tone' => 'red',
                        'module' => 'school_settings',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                    [
                        'name' => 'school.settings.update',
                        'label' => 'تحديث إعدادات المدرسة',
                        'description' => 'صلاحية حساسة لتعديل الإعدادات العامة للمدرسة.',
                        'tone' => 'red',
                        'module' => 'school_settings',
                        'sensitive' => true,
                        'manager_assignable' => false,
                        'dependencies' => [],
                        'related_capability' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     tone: string,
     *     role_names: array<int, string>,
     *     permission_names: array<int, string>,
     *     sensitive: bool
     * }>
     */
    public static function delegationTemplates(): array
    {
        return [
            [
                'key' => 'teacher',
                'label' => 'معلم',
                'description' => 'يلائم المعلم الذي يحتاج دورًا تعليميًا فقط دون صلاحيات تشغيلية إضافية واسعة.',
                'tone' => 'emerald',
                'role_names' => ['teacher'],
                'permission_names' => [],
                'sensitive' => false,
            ],
            [
                'key' => 'attendance_supervisor',
                'label' => 'مشرف حضور',
                'description' => 'مناسب لمتابعة الحضور اليومي داخل المدرسة دون فتح بقية الوحدات الحساسة.',
                'tone' => 'blue',
                'role_names' => [],
                'permission_names' => ['school.student_attendance.manage'],
                'sensitive' => false,
            ],
            [
                'key' => 'student_affairs',
                'label' => 'مسؤول شؤون الطلاب',
                'description' => 'يغطي الطلاب وبنيتهم وإجازاتهم اليومية داخل المدرسة.',
                'tone' => 'cyan',
                'role_names' => [],
                'permission_names' => [
                    'school.student_structure.manage',
                    'school.student_leaves.manage',
                    'school.leave_types.manage',
                ],
                'sensitive' => false,
            ],
            [
                'key' => 'schedule_officer',
                'label' => 'مسؤول جداول',
                'description' => 'مناسب لتشغيل التخطيط الأكاديمي والجداول والتقويم المدرسي.',
                'tone' => 'indigo',
                'role_names' => [],
                'permission_names' => [
                    'school.academic_planning.manage',
                    'school.calendar.manage',
                    'school.holidays.manage',
                ],
                'sensitive' => false,
            ],
            [
                'key' => 'exam_officer',
                'label' => 'مسؤول اختبارات',
                'description' => 'يفتح وحدة الاختبارات بالكامل دون الحاجة لتفويض الجداول أو الحضور.',
                'tone' => 'orange',
                'role_names' => [],
                'permission_names' => ['school.exams.manage'],
                'sensitive' => false,
            ],
            [
                'key' => 'reports_officer',
                'label' => 'مسؤول تقارير',
                'description' => 'مناسب لقراءة تقارير المدرسة، مع إمكانية إضافة التصدير يدويًا عند الحاجة.',
                'tone' => 'slate',
                'role_names' => [],
                'permission_names' => ['school.reports.view'],
                'sensitive' => false,
            ],
            [
                'key' => 'certificate_officer',
                'label' => 'مسؤول الشهادات',
                'description' => 'مناسب لإنشاء قوالب الشهادات وإصدار الشهادات وطباعتها داخل المدرسة دون صلاحيات الإلغاء أو إدارة الأختام.',
                'tone' => 'emerald',
                'role_names' => [],
                'permission_names' => [
                    'certificates.view',
                    'certificates.templates.view',
                    'certificates.templates.create',
                    'certificates.templates.update',
                    'certificates.issue',
                    'certificates.print',
                    'certificates.bulk_issue',
                ],
                'sensitive' => false,
            ],
            [
                'key' => 'administrative_officer',
                'label' => 'إداري شامل',
                'description' => 'يغطي الأعمال الإدارية التشغيلية اليومية داخل المدرسة دون الصلاحيات الحساسة المحجوزة.',
                'tone' => 'violet',
                'role_names' => [],
                'permission_names' => [
                    'school.student_structure.manage',
                    'school.student_attendance.manage',
                    'school.student_leaves.manage',
                    'school.leave_types.manage',
                    'school.calendar.manage',
                    'school.holidays.manage',
                    'school.reports.view',
                    'certificates.view',
                    'certificates.templates.view',
                    'certificates.templates.create',
                    'certificates.templates.update',
                    'certificates.issue',
                    'certificates.print',
                ],
                'sensitive' => false,
            ],
            [
                'key' => 'custom',
                'label' => 'مخصص',
                'description' => 'ابدأ بدون صلاحيات جاهزة ثم خصص التفويض يدويًا بحسب الحاجة.',
                'tone' => 'slate',
                'role_names' => [],
                'permission_names' => [],
                'sensitive' => false,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function groupTypes(): array
    {
        return collect(self::catalog())
            ->pluck('type')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function groupTypeOptions(bool $managerAssignableOnly = false): array
    {
        return collect(self::catalogGroups($managerAssignableOnly))
            ->groupBy('type')
            ->map(function ($groups, string $type): array {
                $first = $groups->first();

                return [
                    'value' => $type,
                    'label' => (string) ($first['type_label'] ?? self::groupTypeLabel($type)),
                    'description' => $type === self::TYPE_EDUCATIONAL
                        ? 'مجموعات تشغيلية تعليمية وأكاديمية قابلة للتفويض داخل المدرسة.'
                        : 'مجموعات تشغيلية إدارية قابلة للتفويض داخل المدرسة.',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalogGroups(bool $managerAssignableOnly = false): array
    {
        return collect(self::catalog())
            ->map(function (array $group) use ($managerAssignableOnly): array {
                $permissions = collect($group['permissions'])
                    ->when($managerAssignableOnly, fn ($permissions) => $permissions->where('manager_assignable', true))
                    ->values()
                    ->all();

                return [
                    'key' => $group['key'],
                    'type' => $group['type'],
                    'type_label' => self::groupTypeLabel($group['type']),
                    'label' => $group['label'],
                    'description' => $group['description'],
                    'tone' => $group['tone'],
                    'permissions' => $permissions,
                ];
            })
            ->filter(fn (array $group): bool => count($group['permissions']) > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function permissionMetadata(bool $managerAssignableOnly = false): array
    {
        return collect(self::catalogGroups($managerAssignableOnly))
            ->flatMap(fn (array $group) => collect($group['permissions'])->mapWithKeys(
                fn (array $permission): array => [
                    $permission['name'] => $permission + [
                        'type' => $group['type'],
                        'type_label' => $group['type_label'],
                        'group_key' => $group['key'],
                        'group_label' => $group['label'],
                    ],
                ]
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function permissionNames(bool $managerAssignableOnly = false): array
    {
        return array_keys(self::permissionMetadata($managerAssignableOnly));
    }

    /**
     * @return array<int, string>
     */
    public static function managerAssignablePermissionNames(?string $groupType = null): array
    {
        if ($groupType === null) {
            return self::permissionNames(true);
        }

        return collect(self::catalogGroups(true))
            ->where('type', self::normalizeGroupType($groupType))
            ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function permissionColumnMap(): array
    {
        return [
            'school.student_structure.manage' => 'can_manage_student_structure',
            'school.student_attendance.manage' => 'can_manage_student_attendance',
            'school.academic_planning.manage' => 'can_manage_academic_planning',
            'school.student_leaves.manage' => 'can_manage_student_leaves',
            'school.leave_types.manage' => 'can_manage_leave_types',
            'school.calendar.manage' => 'can_manage_school_calendar',
            'school.holidays.manage' => 'can_manage_school_holidays',
        ];
    }

    public static function groupTypeLabel(string $groupType): string
    {
        return match ($groupType) {
            self::TYPE_EDUCATIONAL => 'الصلاحيات التعليمية',
            default => 'الصلاحيات الإدارية',
        };
    }

    public static function normalizeGroupType(string $groupType): string
    {
        $normalized = trim($groupType);

        if (!in_array($normalized, self::groupTypes(), true)) {
            throw ValidationException::withMessages([
                'group_type' => 'نوع مجموعة الصلاحيات غير صالح.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $permissionNames
     * @return array<int, string>
     */
    public static function normalizePermissionNames(array $permissionNames, ?string $groupType = null): array
    {
        return self::normalizePermissionSet(
            $permissionNames,
            $groupType !== null ? self::permissionNamesForType(self::normalizeGroupType($groupType), false) : self::permissionNames(false),
            false
        );
    }

    /**
     * @param array<int, mixed> $permissionNames
     * @return array<int, string>
     */
    public static function normalizeManagerAssignablePermissionNames(array $permissionNames, ?string $groupType = null, bool $allowEmpty = true): array
    {
        return self::normalizePermissionSet(
            $permissionNames,
            $groupType !== null ? self::permissionNamesForType(self::normalizeGroupType($groupType), true) : self::permissionNames(true),
            $allowEmpty
        );
    }

    /**
     * @return array<int, string>
     */
    public static function sensitivePermissionNames(bool $managerAssignableOnly = false): array
    {
        return collect(self::permissionMetadata($managerAssignableOnly))
            ->filter(fn (array $permission): bool => (bool) ($permission['sensitive'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function dependencyMap(bool $managerAssignableOnly = false): array
    {
        return collect(self::permissionMetadata($managerAssignableOnly))
            ->mapWithKeys(fn (array $permission, string $permissionName): array => [
                $permissionName => collect($permission['dependencies'] ?? [])
                    ->map(fn ($dependency): string => trim((string) $dependency))
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function permissionNamesForType(string $groupType, bool $managerAssignableOnly): array
    {
        return collect(self::catalogGroups($managerAssignableOnly))
            ->where('type', $groupType)
            ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $permissionNames
     * @param array<int, string> $allowedNames
     * @return array<int, string>
     */
    private static function normalizePermissionSet(array $permissionNames, array $allowedNames, bool $allowEmpty): array
    {
        $normalized = collect($permissionNames)
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            if ($allowEmpty) {
                return [];
            }

            throw ValidationException::withMessages([
                'permission_names' => 'يجب اختيار صلاحية تشغيلية واحدة على الأقل.',
            ]);
        }

        if ($normalized->diff($allowedNames)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permission_names' => 'لا يمكنك منح صلاحيات غير مسموحة ضمن هذا النطاق.',
            ]);
        }

        return self::expandDependencies($normalized->all(), $allowedNames);
    }

    /**
     * @param array<int, string> $permissionNames
     * @param array<int, string> $allowedNames
     * @return array<int, string>
     */
    private static function expandDependencies(array $permissionNames, array $allowedNames): array
    {
        $selected = collect($permissionNames)
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter()
            ->unique()
            ->values();

        $dependencyMap = self::dependencyMap();
        $allowed = collect($allowedNames)->unique()->values();
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($selected->all() as $permissionName) {
                foreach (($dependencyMap[$permissionName] ?? []) as $dependencyName) {
                    if (!$allowed->contains($dependencyName) || $selected->contains($dependencyName)) {
                        continue;
                    }

                    $selected->push($dependencyName);
                    $selected = $selected->unique()->values();
                    $changed = true;
                }
            }
        }

        return $selected->values()->all();
    }
}
