<?php

namespace App\Support;

use App\Models\CertificateTemplate;

class CertificateOptionLibrary
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function types(): array
    {
        return [
            ['value' => CertificateTemplate::TYPE_APPRECIATION, 'label' => 'شهادة شكر وتقدير'],
            ['value' => CertificateTemplate::TYPE_EXCELLENCE, 'label' => 'شهادة تفوق'],
            ['value' => CertificateTemplate::TYPE_ATTENDANCE, 'label' => 'شهادة حضور'],
            ['value' => CertificateTemplate::TYPE_PARTICIPATION, 'label' => 'شهادة مشاركة'],
            ['value' => CertificateTemplate::TYPE_COMPLETION, 'label' => 'شهادة اجتياز'],
            ['value' => CertificateTemplate::TYPE_QURAN, 'label' => 'شهادة حفظ قرآن'],
            ['value' => CertificateTemplate::TYPE_DISCIPLINE, 'label' => 'شهادة سلوك وانضباط'],
            ['value' => CertificateTemplate::TYPE_ACTIVITY_EXCELLENCE, 'label' => 'شهادة تميز في نشاط'],
            ['value' => CertificateTemplate::TYPE_STAGE_COMPLETION, 'label' => 'شهادة نهاية مرحلة'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function typeValues(): array
    {
        return collect(self::types())->pluck('value')->all();
    }

    /**
     * @return array<int, array{key: string, label: string, category: string, preview: string|null}>
     */
    public static function frames(): array
    {
        return [
            ['key' => 'formal-simple', 'label' => 'إطار رسمي بسيط', 'category' => 'رسمي', 'preview' => null],
            ['key' => 'classic-gold', 'label' => 'إطار ذهبي كلاسيكي', 'category' => 'فاخر', 'preview' => null],
            ['key' => 'islamic-geometry', 'label' => 'إطار بزخارف هندسية إسلامية', 'category' => 'إسلامي', 'preview' => null],
            ['key' => 'quran-soft', 'label' => 'إطار هادئ لحفظ القرآن', 'category' => 'قرآني', 'preview' => null],
            ['key' => 'kids-soft', 'label' => 'إطار أطفال للروضة', 'category' => 'أطفال', 'preview' => null],
            ['key' => 'modern-minimal', 'label' => 'إطار حديث بسيط', 'category' => 'حديث', 'preview' => null],
            ['key' => 'excellence-luxury', 'label' => 'إطار فاخر للتفوق', 'category' => 'تفوق', 'preview' => null],
            ['key' => 'administrative-formal', 'label' => 'إطار رسمي إداري', 'category' => 'إداري', 'preview' => null],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function frameKeys(): array
    {
        return collect(self::frames())->pluck('key')->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function fonts(): array
    {
        return [
            ['value' => 'Cairo', 'label' => 'Cairo'],
            ['value' => 'Tajawal', 'label' => 'Tajawal'],
            ['value' => 'Amiri', 'label' => 'Amiri'],
            ['value' => 'Noto Naskh Arabic', 'label' => 'Noto Naskh Arabic'],
            ['value' => 'Noto Kufi Arabic', 'label' => 'Noto Kufi Arabic'],
            ['value' => 'Lateef', 'label' => 'Lateef'],
            ['value' => 'Scheherazade New', 'label' => 'Scheherazade New'],
            ['value' => 'Reem Kufi', 'label' => 'Reem Kufi'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function fontValues(): array
    {
        return collect(self::fonts())->pluck('value')->all();
    }

    /**
     * @return array<string, string>
     */
    public static function phrases(): array
    {
        return [
            CertificateTemplate::TYPE_APPRECIATION => 'تتشرف إدارة {school_name} بمنح هذه الشهادة إلى الطالب/ة {student_name} تقديرًا لجهوده/جهودها وتميزه/تميزها خلال العام الدراسي {academic_year}. نسأل الله له/لها دوام التوفيق والنجاح.',
            CertificateTemplate::TYPE_EXCELLENCE => 'تمنح {school_name} هذه الشهادة إلى الطالب/ة {student_name} تقديرًا لتفوقه/تفوقها الدراسي في {grade_name} خلال {term_name}. سائلين الله له/لها مزيدًا من التميز والنجاح.',
            CertificateTemplate::TYPE_QURAN => 'تشهد {school_name} بأن الطالب/ة {student_name} قد أتم/أتمت بحمد الله حفظ {achievement_detail}. نسأل الله أن يجعل القرآن العظيم ربيع قلبه/قلبها ونور دربه/دربها.',
            CertificateTemplate::TYPE_ATTENDANCE => 'تشهد {school_name} بأن الطالب/ة {student_name} قد حضر/حضرت {activity_name} وذلك بتاريخ {certificate_date}. وقد مُنحت له/لها هذه الشهادة تقديرًا لحضوره/حضورها.',
            CertificateTemplate::TYPE_PARTICIPATION => 'تتشرف {school_name} بمنح هذه الشهادة إلى الطالب/ة {student_name} لمشاركته/لمشاركتها الفعالة في {activity_name}. مع تمنياتنا له/لها بدوام التوفيق.',
            CertificateTemplate::TYPE_COMPLETION => 'تشهد {school_name} بأن الطالب/ة {student_name} قد اجتاز/اجتازت {achievement_detail} بنجاح، وصدرت هذه الشهادة توثيقًا لذلك.',
            CertificateTemplate::TYPE_DISCIPLINE => 'تمنح {school_name} هذه الشهادة إلى الطالب/ة {student_name} تقديرًا لانضباطه/انضباطها وسلوكه/سلوكها المتميز داخل المدرسة.',
            CertificateTemplate::TYPE_ACTIVITY_EXCELLENCE => 'تتشرف {school_name} بمنح الطالب/ة {student_name} شهادة تميز في {activity_name} تقديرًا لمشاركته/لمشاركتها وإبداعه/إبداعها.',
            CertificateTemplate::TYPE_STAGE_COMPLETION => 'تشهد {school_name} بأن الطالب/ة {student_name} قد أتم/أتمت متطلبات {grade_name} بنجاح، ونسأل الله له/لها التوفيق في المرحلة القادمة.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function variables(): array
    {
        return [
            '{student_name}',
            '{recipient_name}',
            '{recipient_type_label}',
            '{student_gender_label}',
            '{school_name}',
            '{grade_name}',
            '{stage_name}',
            '{classroom_name}',
            '{academic_year}',
            '{term_name}',
            '{certificate_date}',
            '{hijri_date}',
            '{gregorian_date}',
            '{manager_name}',
            '{teacher_name}',
            '{activity_name}',
            '{achievement_detail}',
            '{certificate_number}',
        ];
    }

    public static function labelForType(string $type): string
    {
        return collect(self::types())->firstWhere('value', $type)['label'] ?? 'شهادة';
    }
}
