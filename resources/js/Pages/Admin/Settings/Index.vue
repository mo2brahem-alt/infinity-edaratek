<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    Save, Image, Palette, Type, LayoutTemplate, Plus, Trash2, 
    Link as LinkIcon, Columns, Menu, FileText, MonitorPlay, Layers, 
    UploadCloud, File as PageIcon, Edit, ExternalLink, X, Box, Code, Check,
    Sliders, MousePointer, BarChart3, DollarSign, HelpCircle, Home, Settings, Share2, Sparkles
} from 'lucide-vue-next';
import MediaManagerModal from '@/Components/MediaManagerModal.vue';
import SchoolDefaultTemplatesModal from '@/Components/Admin/SchoolDefaultTemplatesModal.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({ initialTab: String, settings: Object, footerColumns: Array, headerMenus: Array, pages: Array, components: Array });
const allowedSettingTabs = ['general', 'home', 'banner', 'components', 'pages', 'header', 'footer'];
const activeTab = ref(allowedSettingTabs.includes(props.initialTab) ? props.initialTab : 'general');
const actionDialog = useActionDialog();
const isSchoolDefaultsModalOpen = ref(false);
const schoolDefaultsModalKey = ref(0);
const settingTabs = computed(() => ([
    { key: 'general', label: 'الهوية العامة', icon: Palette, hint: 'الألوان والشعار والهوية الأساسية للمنصة.' },
    { key: 'default_templates', label: 'القوالب الافتراضية', icon: Layers, hint: 'إدارة القوالب العامة بحسب الدولة ونوع التعليم.' },
    { key: 'home', label: 'محتوى الرئيسية', icon: Home, hint: 'ترتيب الصفحة الرئيسية والأكواد القصيرة.' },
    { key: 'banner', label: 'البنرات', icon: MonitorPlay, hint: 'البنرات الأساسية والمساحات البصرية البارزة.' },
    { key: 'components', label: 'المكونات', icon: Box, hint: 'المقاطع القابلة لإعادة الاستخدام داخل الصفحات.' },
    { key: 'pages', label: 'الصفحات', icon: PageIcon, hint: 'الصفحات الثابتة وروابطها ومحتواها.' },
    { key: 'header', label: 'الهيدر', icon: Menu, hint: 'قائمة الترويسة وروابطها وإعدادات ظهورها.' },
    { key: 'footer', label: 'الفوتر', icon: LayoutTemplate, hint: 'أعمدة وروابط التذييل والمعلومات الختامية.' },
]));
const visibleSettingTabs = computed(() =>
    settingTabs.value.filter((tab) => tab.key !== 'default_templates')
);
const activeTabMeta = computed(() => settingTabs.value.find((tab) => tab.key === activeTab.value) || null);
const settingsOverviewCards = computed(() => ([
    {
        key: 'settings-hub',
        icon: Settings,
        label: 'مركز الإعدادات',
        value: `${visibleSettingTabs.value.length} أقسام`,
        copy: 'تنظيم أوضح للإعدادات الأساسية والمحتوى العام داخل صفحة واحدة قابلة للمسح السريع.',
    },
    {
        key: 'public-content',
        icon: PageIcon,
        label: 'المحتوى العام',
        value: `${props.pages?.length || 0} صفحات / ${props.components?.length || 0} مكونات`,
        copy: 'إدارة الصفحات والمقاطع والبنرات من نفس السياق بدل التنقل بين شاشات متباعدة.',
    },
    {
        key: 'templates',
        icon: Layers,
        label: 'القوالب الافتراضية',
        value: 'الدولة + نوع التعليم',
        copy: 'المحرر الكامل يفتح في نافذة مستقلة حتى تبقى صفحة الإعدادات أخف وأكثر وضوحًا.',
    },
]));
const schoolDefaultsEmbedUrl = computed(() => `${route('admin.school_defaults.index', undefined, false)}?embedded=1&t=${schoolDefaultsModalKey.value}`);
const openSchoolDefaultsModal = () => {
    schoolDefaultsModalKey.value += 1;
    isSchoolDefaultsModalOpen.value = true;
};

// Helper to safely parse JSON
const safeParse = (json) => { try { return JSON.parse(json); } catch (e) { return {}; } };
const componentMobileIcon = (component) => {
    const type = safeParse(component?.content).type;

    if (type === 'banner') return MonitorPlay;
    if (type === 'section_title') return Type;
    if (type === 'pricing') return DollarSign;
    if (type === 'features') return Sparkles;
    if (type === 'stats') return BarChart3;
    if (type === 'cta') return MousePointer;

    return Box;
};

const bannersList = computed(() => props.components?.filter(c => safeParse(c.content).type === 'banner') || []);
const otherComponentsList = computed(() => props.components?.filter(c => safeParse(c.content).type !== 'banner') || []);

const showMediaModal = ref(false);
const currentMediaKey = ref(null);
const allowedMediaType = ref('all');
const mediaTarget = ref('setting');

const openMediaManager = (key, type = 'all', target = 'setting') => { 
    currentMediaKey.value = key; allowedMediaType.value = type; mediaTarget.value = target; showMediaModal.value = true; 
};

const handleMediaSelect = (media) => { 
    const selectedPath = media?.file_path || '';

    if (mediaTarget.value === 'setting') {
        form[currentMediaKey.value] = selectedPath;
    } else if (mediaTarget.value === 'component') {
        if (currentMediaKey.value === 'design_background_image') {
            compForm.designData.backgroundImage = selectedPath;
        } else if (currentMediaKey.value === 'banner_media') {
            compForm.bannerData.media = selectedPath;
        } else if (currentMediaKey.value === 'info_image') {
            compForm.infoData.image = selectedPath;
        }
    }

    showMediaModal.value = false;
};
const getFileUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http') || path.startsWith('/media-files/')) return path;

    const normalized = path
        .replace(/^\/?public\/storage\//i, '')
        .replace(/^\/?storage\//i, '')
        .replace(/^\/+/, '');

    return normalized ? `/media-files/${normalized}` : null;
};

// General Settings
const getInitialForm = () => {
    let fields = {
        site_name: '', site_logo: null, site_icon: null,
        home_background_effects_enabled: false,
        home_background_effect_intensity: 'normal',
        primary_color: '#2563eb', secondary_color: '#1e1b4b',
        bg_color: '#111827', glass_color_1: '#3b82f6', glass_color_2: '#9333ea',
        heading_color: '#ffffff', subheading_color: '#e5e7eb', text_color: '#9ca3af',
        btn_bg_color: '#2563eb', btn_text_color: '#ffffff', btn_style: 'solid', btn_shape: 'rounded-lg', btn_animation: 'hover-scale',
        home_page_content: '', 
        // ✅ إعدادات الهيدر الجديدة
        header_bg_color: '#111827',
        header_text_color: '#ffffff',
        header_link_hover_color: '#2563eb',
        header_show_logo: true,
        header_brand_position: 'left',
        header_height: 80,
        header_logo_size: 40,
        header_logo_width: 40,
        header_logo_height: 40,
        header_logo_padding_inline: 0,
        header_logo_padding_block: 0,
        header_logo_margin_inline: 0,
        header_logo_margin_block: 0,
        header_title_size: 22,
        header_menu_size: 15,
        header_padding_x: 24,
        header_cta_radius: 10,
        header_blur: 14,
        header_border_opacity: 10,
        header_facebook: '', header_twitter: '', header_instagram: '', header_linkedin: '',
        header_contact_text: 'تواصل معنا', header_contact_url: '',
        // إعدادات الفوتر
        footer_text: '', footer_desc: '',
        footer_bg_color: '#1e1b4b', footer_text_color: '#9ca3af', footer_heading_color: '#ffffff',
        footer_padding_top: 64,
        footer_padding_bottom: 32,
        footer_columns_gap: 48,
        footer_title_size: 18,
        footer_text_size: 14,
        footer_link_size: 14,
        footer_align: 'right',
    };
    if (props.settings) Object.values(props.settings).flat().forEach(s => { if(s.value !== null) fields[s.key] = s.value; });
    fields.home_background_effects_enabled = ['1', 1, true, 'true', 'on'].includes(fields.home_background_effects_enabled);
    fields.home_background_effect_intensity = ['subtle', 'normal', 'strong'].includes(fields.home_background_effect_intensity)
        ? fields.home_background_effect_intensity
        : 'normal';
    fields.header_show_logo = !['0', 0, false, 'false', null].includes(fields.header_show_logo);
    fields.header_logo_width = Number(fields.header_logo_width || fields.header_logo_size || 40);
    fields.header_logo_height = Number(fields.header_logo_height || fields.header_logo_size || 40);
    fields.header_logo_padding_inline = Number(fields.header_logo_padding_inline || 0);
    fields.header_logo_padding_block = Number(fields.header_logo_padding_block || 0);
    fields.header_logo_margin_inline = Number(fields.header_logo_margin_inline || 0);
    fields.header_logo_margin_block = Number(fields.header_logo_margin_block || 0);
    return fields;
};
const form = useForm(getInitialForm());
const mediaSettingKeys = ['site_logo', 'site_icon', 'hero_video', 'banner_media'];
const isFileLike = (value) => value instanceof File || (typeof Blob !== 'undefined' && value instanceof Blob);
const settingsPayload = () => {
    const payload = { ...form.data() };

    mediaSettingKeys.forEach((key) => {
        if (isFileLike(payload[key])) return;

        if (key === 'site_icon' && payload[key] === '') {
            return;
        }

        if (typeof payload[key] === 'string' && payload[key].trim() !== '') {
            payload[key] = payload[key].trim();

            return;
        }

        // Omit only truly empty media values; keep persisted/library-selected paths.
        delete payload[key];
    });

    return payload;
};

const submitSettings = () =>
    form.transform(() => settingsPayload()).post(route('admin.settings.update'), {
        preserveScroll: true,
        preserveState: true,
    });
const clearSiteIcon = () => {
    form.site_icon = '';
};
const addToHomeEditor = (code) => { form.home_page_content += `\n${code}\n`; };
const deleteByRoute = async (routeName, id, label = 'العنصر') => {
    const confirmed = await actionDialog.confirm({
        title: `حذف ${label}`,
        message: `سيتم حذف ${label} نهائيًا من الإعدادات الحالية. هل تريد المتابعة؟`,
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(route(routeName, id));
};

// Pages
const showPageModal = ref(false); const isEditingPage = ref(false); const pageForm = useForm({ id: null, title: '', slug: '', content: '' }); 
const openCreatePage = () => { isEditingPage.value = false; pageForm.reset(); showPageModal.value = true; }; 
const openEditPage = (page) => { isEditingPage.value = true; pageForm.id = page.id; pageForm.title = page.title; pageForm.slug = page.slug; pageForm.content = page.content; showPageModal.value = true; }; 
const submitPage = () => isEditingPage.value ? pageForm.put(route('admin.pages.update', pageForm.id), { onSuccess: () => showPageModal.value = false }) : pageForm.post(route('admin.pages.store'), { onSuccess: () => showPageModal.value = false }); 
const deletePage = (id) => deleteByRoute('admin.pages.destroy', id, 'الصفحة');

// Components
const showCompModal = ref(false); const isEditingComp = ref(false);
const getDefaultDesignData = () => ({
    marginTop: 0,
    marginBottom: 0,
    paddingTop: 80,
    paddingBottom: 80,
    backgroundType: 'none',
    backgroundColor: '#111827',
    backgroundGradient: 'linear-gradient(135deg, #111827 0%, #1f2937 100%)',
    backgroundImage: '',
    backgroundOpacity: 100,
    textAlign: 'text-right',
    titleSize: 44,
    subtitleSize: 20,
    bodySize: 18,
    titleWeight: 700,
    bodyWeight: 400,
    titleLineHeight: 1.2,
    bodyLineHeight: 1.8,
    imageWidth: 100,
    imageHeight: 420,
    imageFit: 'cover',
    imagePosition: 'center center',
    imageRadius: 24,
    imageDirection: 'normal',
});
const compForm = useForm({ 
    id: null, name: '', shortcode: '', content: '', type: 'html', 
    quizQuestions: [],
    designData: getDefaultDesignData(),
    infoData: { title: '', text: '', image: '', layout: 'image_right', btnText: '', btnUrl: '', bgColor: 'transparent', animation: 'fade-up', titleColor: '#ffffff', textColor: '#9ca3af', btnBgColor: '#2563eb', btnTextColor: '#ffffff' },
    bannerData: { 
        title: '', subtitle: '', mediaType: 'image', media: '', btnText: '', btnUrl: '', height: 'min-h-[600px]', overlay: 'bg-black/50', alignment: 'text-center', titleColor: '#ffffff', subtitleColor: '#e5e7eb', btnBgColor: '#2563eb', btnTextColor: '#ffffff',
        glassBgColor: '#111827', glassOpacity: 30, glassBlur: 10, glassHeight: 320,
        glassMarginTop: 0, glassMarginBottom: 0, glassMarginRight: 0, glassMarginLeft: 0
    },
    titleData: { title: '', subtitle: '', style: 'gradient', alignment: 'text-center', titleColor: '#ffffff', subtitleColor: '#3b82f6' },
    statsData: { title: '', titleColor: '#ffffff', cardBgColor: 'bg-gray-800', numberColor: '#3b82f6', labelColor: '#9ca3af', stats: [{ number: '100', label: 'عميل', suffix: '+' }] },
    faqData: { title: '', titleColor: '#ffffff', questionColor: '#ffffff', answerColor: '#9ca3af', items: [{ question: '', answer: '' }] },
    pricingData: {
        title: '',
        subtitle: '',
        titleColor: '#ffffff',
        subtitleColor: '#9ca3af',
        cardBgColor: 'bg-gray-900',
        priceColor: '#ffffff',
        plans_flow: 'right',
        plans: [{
            name: 'Basic',
            price: '99',
            monthly_price: '99',
            yearly_price: '1188',
            included_users_count: 10,
            extra_user_monthly_price: '0',
            title_alignment: 'right',
            price_alignment: 'right',
            features_alignment: 'right',
            features: [{ text: 'ميزة 1', included: true }],
            isFeatured: false,
        }],
    }
});
const canUseDesignControls = computed(() => !['html', 'quiz'].includes(compForm.type));
const componentUsesImage = computed(() => ['banner', 'info_section', 'section_title'].includes(compForm.type));

const normalizePricingPlans = (plans = []) => (plans || []).map((plan) => ({
    ...plan,
    price: plan.price ?? plan.monthly_price ?? plan.monthlyPrice ?? '',
    monthly_price: plan.monthly_price ?? plan.monthlyPrice ?? plan.price ?? '',
    yearly_price: plan.yearly_price ?? plan.yearlyPrice ?? '',
    included_users_count: plan.included_users_count ?? plan.includedUsersCount ?? 0,
    extra_user_monthly_price: plan.extra_user_monthly_price ?? plan.extraUserMonthlyPrice ?? 0,
    billing_cycle: plan.billing_cycle || plan.billingCycle || 'MONTHLY',
    role_type: plan.role_type || plan.roleType || 'SUPERVISOR',
    plan_id: plan.plan_id ?? plan.planId ?? '',
    url: plan.url || '',
    title_alignment: plan.title_alignment || plan.titleAlignment || 'right',
    price_alignment: plan.price_alignment || plan.priceAlignment || 'right',
    features_alignment: plan.features_alignment || plan.featuresAlignment || 'right',
}));

const normalizePricingData = (pricingData = {}) => ({
    ...pricingData,
    plans_flow: pricingData.plans_flow || pricingData.plansFlow || 'right',
    plans: normalizePricingPlans(pricingData.plans),
});

const openCreateComp = (type) => { 
    isEditingComp.value = false; compForm.reset(); compForm.type = type;
    compForm.designData = getDefaultDesignData();
    compForm.shortcode = `[${type}-${Math.floor(Math.random()*1000)}]`;
    if (type === 'pricing') {
        compForm.pricingData = normalizePricingData(compForm.pricingData);
    }
    showCompModal.value = true; 
};

const openEditComp = (comp) => { 
    isEditingComp.value = true; compForm.id = comp.id; compForm.name = comp.name; compForm.shortcode = comp.shortcode; 
    try {
        const json = JSON.parse(comp.content);
        compForm.designData = { ...getDefaultDesignData(), ...(json.design || {}) };
        if (json.type) compForm.type = json.type;
        if (json.type === 'banner') compForm.bannerData = { ...compForm.bannerData, ...json };
        else if (json.type === 'info_section') compForm.infoData = { ...compForm.infoData, ...json };
        else if (json.type === 'section_title') compForm.titleData = { ...compForm.titleData, ...json };
        else if (json.type === 'stats') compForm.statsData = { ...compForm.statsData, ...json };
        else if (json.type === 'faq') compForm.faqData = { ...compForm.faqData, ...json };
        else if (json.type === 'pricing') {
            const mergedPricingData = { ...compForm.pricingData, ...json };
            compForm.pricingData = normalizePricingData(mergedPricingData);
        }
        else if (Array.isArray(json)) { compForm.type = 'quiz'; compForm.quizQuestions = json; }
        else { compForm.type = 'html'; compForm.content = comp.content; }
    } catch(e) { compForm.type = 'html'; compForm.content = comp.content; compForm.designData = getDefaultDesignData(); }
    showCompModal.value = true; 
};

const submitComp = () => {
    let content = '';
    if (compForm.type === 'html') content = compForm.content;
    else if (compForm.type === 'quiz') content = JSON.stringify(compForm.quizQuestions);
    else if (compForm.type === 'banner') content = JSON.stringify({ type: 'banner', ...compForm.bannerData, design: compForm.designData });
    else if (compForm.type === 'info_section') content = JSON.stringify({ type: 'info_section', ...compForm.infoData, design: compForm.designData });
    else if (compForm.type === 'section_title') content = JSON.stringify({ type: 'section_title', ...compForm.titleData, design: compForm.designData });
    else if (compForm.type === 'stats') content = JSON.stringify({ type: 'stats', ...compForm.statsData, design: compForm.designData });
    else if (compForm.type === 'faq') content = JSON.stringify({ type: 'faq', ...compForm.faqData, design: compForm.designData });
    else if (compForm.type === 'pricing') {
        const normalizedPricingData = normalizePricingData(compForm.pricingData);
        content = JSON.stringify({ type: 'pricing', ...normalizedPricingData, design: compForm.designData });
    }
    compForm.content = content;
    const action = isEditingComp.value ? route('admin.components.update', compForm.id) : route('admin.components.store');
    const method = isEditingComp.value ? 'put' : 'post';
    compForm[method](action, { onSuccess: () => showCompModal.value = false });
};

const deleteComp = (id) => deleteByRoute('admin.components.destroy', id, 'المكوّن');
const copyShortcode = async (code) => {
    await navigator.clipboard.writeText(code);
    await actionDialog.alert({
        title: 'تم نسخ الكود القصير',
        message: `تم نسخ ${code} ويمكنك استخدامه الآن داخل المحتوى أو الصفحات.`,
        confirmText: 'حسنًا',
        variant: 'success',
    });
};

// Dynamic form helpers
const addStat = () => {
    compForm.statsData.stats.push({ number: '0', label: '', suffix: '' });
};

const removeStat = (index) => {
    compForm.statsData.stats.splice(index, 1);
};

const addPlan = () => {
    compForm.pricingData.plans.push({
        name: 'New Plan',
        price: '',
        monthly_price: '',
        yearly_price: '',
        included_users_count: 10,
        extra_user_monthly_price: '',
        billing_cycle: 'MONTHLY',
        role_type: 'SUPERVISOR',
        plan_id: '',
        url: '',
        title_alignment: 'right',
        price_alignment: 'right',
        features_alignment: 'right',
        features: [{ text: 'ميزة جديدة', included: true }],
        isFeatured: false,
    });
};

const removePlan = (planIndex) => {
    compForm.pricingData.plans.splice(planIndex, 1);
};

const addFeature = (planIndex) => {
    compForm.pricingData.plans[planIndex].features.push({ text: '', included: true });
};

const removeFeature = (planIndex, featureIndex) => {
    compForm.pricingData.plans[planIndex].features.splice(featureIndex, 1);
};

const addFaq = () => {
    compForm.faqData.items.push({ question: '', answer: '' });
};

const removeFaq = (index) => {
    compForm.faqData.items.splice(index, 1);
};

// Header & Footer
const colForm = useForm({ title: '' }); const footerItemForm = useForm({ footer_column_id: null, label: '', url: '#' }); const showFooterItemModal = ref(false); const submitCol = () => colForm.post(route('admin.footer.column.store'), { onSuccess: () => colForm.reset() }); const openFooterItemModal = (id) => { footerItemForm.footer_column_id = id; showFooterItemModal.value = true; }; const submitFooterItem = () => footerItemForm.post(route('admin.footer.item.store'), { onSuccess: () => { showFooterItemModal.value = false; footerItemForm.reset(); }}); const deleteCol = (id) => deleteByRoute('admin.footer.column.delete', id, 'عمود الفوتر'); const deleteFooterItem = (id) => deleteByRoute('admin.footer.item.delete', id, 'رابط الفوتر');
const headerMenuForm = useForm({ title: '', url: '#' }); const headerItemForm = useForm({ header_menu_id: null, label: '', url: '#' }); const showHeaderItemModal = ref(false); const submitHeaderMenu = () => headerMenuForm.post(route('admin.header.menu.store'), { onSuccess: () => headerMenuForm.reset() }); const openHeaderItemModal = (id) => { headerItemForm.header_menu_id = id; showHeaderItemModal.value = true; }; const submitHeaderItem = () => headerItemForm.post(route('admin.header.item.store'), { onSuccess: () => { showHeaderItemModal.value = false; headerItemForm.reset(); }}); const deleteHeaderMenu = (id) => deleteByRoute('admin.header.menu.delete', id, 'قائمة الهيدر'); const deleteHeaderItem = (id) => deleteByRoute('admin.header.item.delete', id, 'رابط الهيدر');
</script>

<template>
    <Head title="مظهر وإعدادات الموقع" />
    <AdminLayout>
        <MediaManagerModal :show="showMediaModal" :allowedType="allowedMediaType" @close="showMediaModal = false" @select="handleMediaSelect" />
        <SchoolDefaultTemplatesModal :open="isSchoolDefaultsModalOpen" :src="schoolDefaultsEmbedUrl" @close="isSchoolDefaultsModalOpen = false" />
        
        <div class="ui-page-shell ui-settings-shell mb-8">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <span class="ui-page-kicker"><Settings class="h-4 w-4" /> مركز إعدادات الموقع</span>
                        <h1 class="ui-page-title">مظهر وإعدادات الموقع</h1>
                        <p class="ui-page-copy">أصبحت الإعدادات موزعة إلى مجموعات أوضح مع وصول أسرع إلى القوالب الافتراضية والمحتوى العام، حتى تبقى الصفحة أسهل في المسح البصري وأقل ازدحامًا على الجوال والتابلت.</p>
                    </div>

                    <div class="ui-page-actions">
                        <button
                            type="button"
                            class="ui-page-action ui-page-action--primary"
                            @click="openSchoolDefaultsModal"
                        >
                            <Layers class="h-4 w-4" />
                            <span>إدارة القوالب الافتراضية</span>
                        </button>
                        <button
                            type="button"
                            class="ui-page-action ui-page-action--ghost"
                            @click="activeTab = 'home'"
                        >
                            <Home class="h-4 w-4" />
                            <span>تحرير محتوى الرئيسية</span>
                        </button>
                    </div>
                </div>

                <div class="ui-page-context-grid">
                    <article
                        v-for="card in settingsOverviewCards"
                        :key="card.key"
                        class="ui-page-context-card text-right"
                    >
                        <div class="ui-page-context-head">
                            <div>
                                <p class="ui-page-context-label">{{ card.label }}</p>
                                <p class="ui-page-context-value">{{ card.value }}</p>
                            </div>
                            <span class="ui-page-context-icon">
                                <component :is="card.icon" class="h-5 w-5" />
                            </span>
                        </div>
                        <p class="ui-page-context-copy">{{ card.copy }}</p>
                    </article>
                </div>
            </section>

            <nav class="ui-settings-nav" aria-label="أقسام إعدادات الموقع">
                <button
                    v-for="tab in visibleSettingTabs"
                    :key="tab.key"
                    type="button"
                    class="ui-settings-tab"
                    :aria-current="activeTab === tab.key ? 'page' : undefined"
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                    <span class="flex-1 text-right">
                        <span class="block font-bold">{{ tab.label }}</span>
                        <span class="mt-1 block text-xs opacity-75">{{ tab.hint }}</span>
                    </span>
                </button>
            </nav>

            <AppInlineAlert
                variant="info"
                :title="activeTabMeta?.label || 'إعدادات الموقع'"
                :message="activeTabMeta?.hint || 'اختر القسم الذي تريد تعديله من التبويبات الظاهرة في الأعلى.'"
            />
        </div>

        <div v-show="activeTab === 'general'" class="ui-settings-panel">
             <form @submit.prevent="submitSettings" class="space-y-8 animate-fade-in pb-20">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-blue-400"><Image class="w-5 h-5" /> الشعار وأيقونة الموقع</h3>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-bold text-white">شعار الموقع</p>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">يُستخدم في الهيدر والواجهة العامة.</p>
                                </div>
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded bg-gray-900">
                                    <img v-if="form.site_logo" :src="getFileUrl(form.site_logo)" class="h-full w-full object-contain" alt="معاينة شعار الموقع">
                                </div>
                            </div>
                            <button type="button" @click="openMediaManager('site_logo', 'image')" class="mt-4 rounded bg-blue-600 px-3 py-1.5 text-sm font-bold text-white transition hover:bg-blue-700">تغيير الشعار</button>
                            <p v-if="form.errors.site_logo" class="mt-2 text-xs text-red-400">{{ form.errors.site_logo }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-bold text-white">أيقونة الموقع</p>
                                    <p class="mt-1 text-xs leading-6 text-gray-400">تظهر كأيقونة للمتصفح والتبويب عند توفرها.</p>
                                </div>
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gray-900 ring-1 ring-white/10">
                                    <img v-if="form.site_icon" :src="getFileUrl(form.site_icon)" class="h-full w-full object-contain p-2" alt="معاينة أيقونة الموقع">
                                    <Image v-else class="h-6 w-6 text-gray-500" aria-hidden="true" />
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" @click="openMediaManager('site_icon', 'image')" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-bold text-white transition hover:bg-blue-700">اختيار الأيقونة</button>
                                <button v-if="form.site_icon" type="button" @click="clearSiteIcon" class="rounded border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-sm font-bold text-red-100 transition hover:bg-red-500/15">حذف الأيقونة</button>
                            </div>
                            <p v-if="form.errors.site_icon" class="mt-2 text-xs text-red-400">{{ form.errors.site_icon }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6"><h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-blue-400"><MousePointer class="w-5 h-5" /> إعدادات الأزرار</h3><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"><div><label class="block text-sm text-gray-400 mb-2">لون الخلفية</label><div class="flex gap-2"><input v-model="form.btn_bg_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.btn_bg_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">لون النص</label><div class="flex gap-2"><input v-model="form.btn_text_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.btn_text_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">ستايل الزر</label><select v-model="form.btn_style" class="w-full bg-gray-800 border border-gray-700 rounded-lg text-white p-2"><option value="solid">عادي (Solid)</option><option value="glass">زجاجي (Glass)</option><option value="gradient">تدرج (Gradient)</option><option value="outline">إطار فقط (Outline)</option></select></div><div><label class="block text-sm text-gray-400 mb-2">شكل الحواف</label><select v-model="form.btn_shape" class="w-full bg-gray-800 border border-gray-700 rounded-lg text-white p-2"><option value="rounded-none">مربع</option><option value="rounded-md">دائري قليلاً</option><option value="rounded-xl">دائري جداً</option><option value="rounded-full">بيضاوي (Pill)</option></select></div><div><label class="block text-sm text-gray-400 mb-2">تأثير الحركة</label><select v-model="form.btn_animation" class="w-full bg-gray-800 border border-gray-700 rounded-lg text-white p-2"><option value="none">بدون</option><option value="hover-scale">تكبير</option><option value="hover-glow">توهج</option><option value="hover-lift">رفع</option></select></div><div class="flex items-end justify-center pb-1"><button type="button" :class="[form.btn_shape, {'transform hover:scale-105': form.btn_animation === 'hover-scale', 'hover:shadow-lg hover:shadow-current': form.btn_animation === 'hover-glow', '-translate-y-1': form.btn_animation === 'hover-lift'}]" :style="{ backgroundColor: form.btn_bg_color, color: form.btn_text_color, border: form.btn_style === 'outline' ? '1px solid currentColor' : 'none', background: form.btn_style === 'gradient' ? `linear-gradient(45deg, ${form.btn_bg_color}, #9333ea)` : (form.btn_style === 'glass' ? `color-mix(in srgb, ${form.btn_bg_color}, transparent 20%)` : form.btn_bg_color), backdropFilter: form.btn_style === 'glass' ? 'blur(10px)' : 'none' }" class="px-6 py-2 transition-all duration-300 font-bold">معاينة</button></div></div></div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6"><h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-purple-400"><Palette class="w-5 h-5" /> الألوان والخلفيات</h3><div class="grid grid-cols-1 md:grid-cols-3 gap-6"><div><label class="block text-sm text-gray-400 mb-2">لون الخلفية</label><div class="flex gap-2"><input v-model="form.bg_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.bg_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">اللون الأساسي</label><div class="flex gap-2"><input v-model="form.primary_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.primary_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">اللون الثانوي</label><div class="flex gap-2"><input v-model="form.secondary_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.secondary_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">شكل زجاجي 1</label><div class="flex gap-2"><input v-model="form.glass_color_1" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.glass_color_1" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">شكل زجاجي 2</label><div class="flex gap-2"><input v-model="form.glass_color_2" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.glass_color_2" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div></div></div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
                    <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-cyan-300"><Sparkles class="h-5 w-5" /> مؤثرات الصفحة الرئيسية</h3>
                    <label class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-gray-800/80 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-right">
                            <span class="block text-sm font-bold text-white">تفعيل مؤثرات الخلفية</span>
                            <span class="mt-1 block text-xs leading-6 text-gray-400">يضيف توهجًا وحركة خفيفة في خلفية الصفحة الرئيسية فقط مع احترام تقليل الحركة.</span>
                        </span>
                        <input v-model="form.home_background_effects_enabled" type="checkbox" class="h-5 w-5 rounded border-gray-600 bg-gray-900 text-cyan-500 focus:ring-cyan-400">
                    </label>
                    <div class="mt-4 rounded-2xl border border-white/10 bg-gray-800/80 p-4">
                        <label class="block text-sm font-bold text-white" for="home_background_effect_intensity">قوة حركة التأثير</label>
                        <p class="mt-1 text-xs leading-6 text-gray-400">اختر درجة الحركة المناسبة لخلفية الصفحة الرئيسية.</p>
                        <select
                            id="home_background_effect_intensity"
                            v-model="form.home_background_effect_intensity"
                            :disabled="!form.home_background_effects_enabled"
                            class="mt-3 w-full rounded-xl border border-gray-700 bg-gray-900 px-3 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <option value="subtle">هادئة</option>
                            <option value="normal">متوسطة</option>
                            <option value="strong">واضحة</option>
                        </select>
                    </div>
                    <p v-if="form.errors.home_background_effects_enabled" class="mt-2 text-xs text-red-400">{{ form.errors.home_background_effects_enabled }}</p>
                    <p v-if="form.errors.home_background_effect_intensity" class="mt-2 text-xs text-red-400">{{ form.errors.home_background_effect_intensity }}</p>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6"><h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-yellow-400"><Type class="w-5 h-5" /> ألوان النصوص</h3><div class="grid grid-cols-1 md:grid-cols-3 gap-6"><div><label class="block text-sm text-gray-400 mb-2">العناوين الكبيرة</label><div class="flex gap-2"><input v-model="form.heading_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.heading_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">العناوين الفرعية</label><div class="flex gap-2"><input v-model="form.subheading_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.subheading_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div><div><label class="block text-sm text-gray-400 mb-2">النصوص العادية</label><div class="flex gap-2"><input v-model="form.text_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.text_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div></div></div>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6"><h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-green-400"><FileText class="w-5 h-5" /> النصوص العامة</h3><div><label class="block text-sm text-gray-400 mb-1">اسم الموقع</label><input v-model="form.site_name" type="text" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-white"></div></div>
                <div class="ui-settings-actions">
                    <button :disabled="form.processing" type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-green-600 px-6 py-3 font-bold text-white shadow-2xl transition hover:bg-green-700 sm:w-auto">
                        <Save class="w-5 h-5" /> حفظ الإعدادات
                    </button>
                </div>
            </form>
        </div>

        <div v-show="activeTab === 'default_templates'" class="ui-settings-panel animate-fade-in pb-20">
            <section class="rounded-3xl border border-emerald-500/20 bg-gradient-to-l from-slate-950 via-slate-900 to-emerald-950/60 p-6 text-right">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-300">
                            <Layers class="h-4 w-4" />
                            <span>القوالب العامة للمدارس</span>
                        </span>
                        <h3 class="mt-3 text-2xl font-black text-white">إدارة القوالب العامة بحسب الدولة ونوع التعليم</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-300">
                            من هنا تُدار القوالب الافتراضية بحسب الدولة ونوع التعليم، ثم تُراجع وتُحرر وتُحفظ قبل نسخها لاحقًا إلى المدارس عند التهيئة الأولى.
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-5 py-3 text-sm font-black text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400"
                            @click="openSchoolDefaultsModal"
                        >
                            <Plus class="h-4 w-4" />
                            <span>إضافة قالب جديد</span>
                        </button>
                    </div>
                </div>
            </section>

            <AppInlineAlert
                variant="info"
                title="القوالب الافتراضية أصبحت ضمن الإعدادات العامة"
                message="يفتح محرر القوالب داخل نافذة مستقلة حتى تظل صفحة الإعدادات أخف وأسهل في المسح البصري، مع بقاء الإنشاء والتحرير والمراجعة في نفس السياق الإداري."
            />
        </div>

        <div v-show="activeTab === 'home'" class="ui-settings-panel pb-20 animate-fade-in">
            <form @submit.prevent="submitSettings">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
                    <div class="flex justify-between items-start mb-6"><div><h3 class="text-lg font-bold text-white flex items-center gap-2"><Home class="w-5 h-5 text-blue-400" /> محتوى الصفحة الرئيسية</h3><p class="text-gray-400 text-sm mt-1">اكتب الأكواد القصيرة هنا.</p></div><button type="submit" :disabled="form.processing" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-lg flex items-center gap-2"><Save class="w-4 h-4" /> حفظ</button></div>
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2"><textarea v-model="form.home_page_content" class="h-[320px] w-full rounded-xl border border-gray-700 bg-gray-800 p-4 text-sm leading-relaxed text-white focus:ring-2 focus:ring-blue-500 resize-none md:h-[500px] font-mono" placeholder="[banner-1]&#10;[contact-form]"></textarea></div>
                        <div class="max-h-[18rem] overflow-y-auto rounded-xl border border-gray-700 bg-gray-800 p-4 md:h-[500px] md:max-h-none">
                            <h4 class="text-white font-bold mb-4 border-b border-gray-700 pb-2">المكونات المتاحة</h4>
                            <div v-if="props.components && props.components.length > 0">
                                <button v-for="comp in props.components" :key="comp.id" type="button" class="group mb-2 flex w-full items-center justify-between rounded border border-gray-700 bg-gray-900 p-2 text-right transition hover:border-blue-500" :aria-label="`إضافة المكوّن ${comp.name} إلى الصفحة الرئيسية`" @click="addToHomeEditor(comp.shortcode)">
                                    <span class="truncate text-sm text-white">{{ comp.name }}</span>
                                    <span class="rounded bg-black/30 px-2 py-1 font-mono text-xs text-yellow-500">{{ comp.shortcode }}</span>
                                    <Plus class="h-4 w-4 text-blue-500 opacity-0 transition group-hover:opacity-100" />
                                </button>
                            </div>
                            <AppStatePanel
                                v-else
                                compact
                                title="لا توجد مكونات مضافة بعد"
                                description="أضف مكوّنًا من تبويب المكونات أو أنشئ بانرًا جديدًا ثم أدرج كوده القصير هنا."
                            />
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div v-show="activeTab === 'banner'" class="pb-20 animate-fade-in">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 class="flex items-center gap-2 text-lg font-bold text-white"><MonitorPlay class="h-5 w-5 text-red-400" /> إدارة البنرات</h3></div>
                <button @click="openCreateComp('banner')" class="flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"><Plus class="h-4 w-4" /> إضافة بنر جديد</button>
            </div>

            <div class="ui-mobile-card-list">
                <article v-for="comp in bannersList" :key="`banner-mobile-${comp.id}`" class="ui-mobile-row-card space-y-4 text-right">
                    <div class="space-y-3">
                        <div class="ui-mobile-card-symbol mx-auto">
                            <component :is="componentMobileIcon(comp)" class="h-5 w-5" />
                        </div>
                        <h4 class="font-bold text-white">{{ comp.name }}</h4>
                        <p class="ui-mobile-row-label mt-2">الكود القصير</p>
                        <button type="button" class="mt-1 rounded border border-gray-700 bg-gray-900 px-2 py-1 font-mono text-xs text-yellow-500" @click="copyShortcode(comp.shortcode)">{{ comp.shortcode }}</button>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="ui-icon-button" :aria-label="`تعديل البنر ${comp.name}`" @click="openEditComp(comp)"><Edit class="h-4 w-4" /></button>
                        <button type="button" class="ui-icon-button" :aria-label="`حذف البنر ${comp.name}`" @click="deleteComp(comp.id)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-hidden rounded-xl border border-gray-700 bg-gray-800/50 md:block">
                <table class="w-full text-right">
                    <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                        <tr><th class="px-6 py-4">اسم البنر</th><th class="px-6 py-4">الكود القصير</th><th class="px-6 py-4">الإجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <tr v-for="comp in bannersList" :key="comp.id" class="hover:bg-white/5">
                            <td class="px-6 py-4 font-bold text-white">{{ comp.name }}</td>
                            <td class="px-6 py-4"><button type="button" @click="copyShortcode(comp.shortcode)" class="rounded border border-gray-700 bg-gray-900 px-2 py-1 font-mono text-xs text-yellow-500 transition hover:bg-black">{{ comp.shortcode }}</button></td>
                            <td class="flex gap-3 px-6 py-4">
                                <button type="button" :aria-label="`تعديل البنر ${comp.name}`" @click="openEditComp(comp)" class="text-blue-400 hover:text-blue-300"><Edit class="h-4 w-4" /></button>
                                <button type="button" :aria-label="`حذف البنر ${comp.name}`" @click="deleteComp(comp.id)" class="text-red-400 hover:text-red-300"><Trash2 class="h-4 w-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-show="activeTab === 'components'" class="pb-20 animate-fade-in">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white"><Box class="w-5 h-5 text-purple-400" /> إدارة المكونات</h3>
            </div>
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <button type="button" @click="openCreateComp('banner')" class="flex flex-col items-center gap-2 rounded-xl border border-red-600/50 bg-red-600/20 p-3 text-red-400 transition hover:bg-red-600 hover:text-white"><MonitorPlay class="w-6 h-6" /> <span class="text-xs font-bold">بنر</span></button>
                <button type="button" @click="openCreateComp('stats')" class="flex flex-col items-center gap-2 rounded-xl border border-blue-600/50 bg-blue-600/20 p-3 text-blue-400 transition hover:bg-blue-600 hover:text-white"><BarChart3 class="w-6 h-6" /> <span class="text-xs font-bold">إحصائيات</span></button>
                <button type="button" @click="openCreateComp('pricing')" class="flex flex-col items-center gap-2 rounded-xl border border-green-600/50 bg-green-600/20 p-3 text-green-400 transition hover:bg-green-600 hover:text-white"><DollarSign class="w-6 h-6" /> <span class="text-xs font-bold">باقات</span></button>
                <button type="button" @click="openCreateComp('faq')" class="flex flex-col items-center gap-2 rounded-xl border border-yellow-600/50 bg-yellow-600/20 p-3 text-yellow-400 transition hover:bg-yellow-600 hover:text-white"><HelpCircle class="w-6 h-6" /> <span class="text-xs font-bold">أسئلة</span></button>
                <button type="button" @click="openCreateComp('info_section')" class="flex flex-col items-center gap-2 rounded-xl border border-purple-600/50 bg-purple-600/20 p-3 text-purple-400 transition hover:bg-purple-600 hover:text-white"><LayoutTemplate class="w-6 h-6" /> <span class="text-xs font-bold">صورة ونص</span></button>
                <button type="button" @click="openCreateComp('section_title')" class="flex flex-col items-center gap-2 rounded-xl border border-gray-600/50 bg-gray-600/20 p-3 text-gray-400 transition hover:bg-gray-600 hover:text-white"><Type class="w-6 h-6" /> <span class="text-xs font-bold">عنوان</span></button>
            </div>

            <div class="ui-mobile-card-list">
                <article v-for="comp in otherComponentsList" :key="`component-mobile-${comp.id}`" class="ui-mobile-row-card space-y-4 text-right">
                    <div class="space-y-3">
                        <div class="ui-mobile-card-symbol mx-auto">
                            <component :is="componentMobileIcon(comp)" class="h-5 w-5" />
                        </div>
                        <h4 class="font-bold text-white">{{ comp.name }}</h4>
                        <p class="ui-mobile-row-label">النوع</p>
                        <span class="inline-flex rounded-full bg-gray-700 px-2 py-1 text-xs text-gray-300">{{ safeParse(comp.content).type || 'html' }}</span>
                        <p class="ui-mobile-row-label pt-1">الكود القصير</p>
                        <button type="button" class="rounded border border-gray-700 bg-gray-900 px-2 py-1 font-mono text-xs text-yellow-500" @click="copyShortcode(comp.shortcode)">{{ comp.shortcode }}</button>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="ui-icon-button" :aria-label="`تعديل المكوّن ${comp.name}`" @click="openEditComp(comp)"><Edit class="w-4 h-4" /></button>
                        <button type="button" class="ui-icon-button" :aria-label="`حذف المكوّن ${comp.name}`" @click="deleteComp(comp.id)"><Trash2 class="w-4 h-4" /></button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-hidden rounded-xl border border-gray-700 bg-gray-800/50 lg:block">
                <table class="w-full text-right">
                    <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                        <tr><th class="px-6 py-4">الاسم</th><th class="px-6 py-4">النوع</th><th class="px-6 py-4">الكود</th><th class="px-6 py-4">الإجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <tr v-for="comp in otherComponentsList" :key="comp.id" class="hover:bg-white/5">
                            <td class="px-6 py-4 text-white font-bold">{{ comp.name }}</td>
                            <td class="px-6 py-4"><span class="rounded bg-gray-700 px-2 py-1 text-xs text-gray-300">{{ safeParse(comp.content).type || 'html' }}</span></td>
                            <td class="px-6 py-4"><button type="button" @click="copyShortcode(comp.shortcode)" class="font-mono text-xs text-yellow-500">{{ comp.shortcode }}</button></td>
                            <td class="px-6 py-4">
                                <div class="flex gap-3">
                                    <button type="button" :aria-label="`تعديل المكوّن ${comp.name}`" @click="openEditComp(comp)" class="text-blue-400 hover:text-blue-300"><Edit class="w-4 h-4" /></button>
                                    <button type="button" :aria-label="`حذف المكوّن ${comp.name}`" @click="deleteComp(comp.id)" class="text-red-400 hover:text-red-300"><Trash2 class="w-4 h-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-show="activeTab === 'pages'" class="pb-20 animate-fade-in">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-bold text-white">الصفحات الثابتة</h3>
                <button type="button" @click="openCreatePage" class="flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-bold text-white"><Plus class="w-4 h-4" /> إضافة</button>
            </div>

            <div class="ui-mobile-card-list">
                <article v-for="page in pages" :key="`page-mobile-${page.id}`" class="ui-mobile-row-card space-y-4 text-right">
                    <div class="space-y-3">
                        <div class="ui-mobile-card-symbol mx-auto">
                            <PageIcon class="h-5 w-5" />
                        </div>
                        <h4 class="font-bold text-white">{{ page.title }}</h4>
                        <p v-if="page.slug" class="text-xs text-gray-400" dir="ltr">{{ page.slug }}</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="ui-icon-button" :aria-label="`تعديل الصفحة ${page.title}`" @click="openEditPage(page)"><Edit class="w-4 h-4" /></button>
                        <button type="button" class="ui-icon-button" :aria-label="`حذف الصفحة ${page.title}`" @click="deletePage(page.id)"><Trash2 class="w-4 h-4" /></button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-hidden rounded-xl border border-gray-700 bg-gray-800/50 lg:block">
                <table class="w-full text-right">
                    <tbody class="divide-y divide-gray-700">
                        <tr v-for="page in pages" :key="page.id" class="hover:bg-white/5">
                            <td class="px-6 py-4 text-white font-bold">{{ page.title }}</td>
                            <td class="px-6 py-4">
                                <div class="flex gap-3">
                                    <button type="button" :aria-label="`تعديل الصفحة ${page.title}`" @click="openEditPage(page)" class="text-blue-400 hover:text-blue-300"><Edit class="w-4 h-4" /></button>
                                    <button type="button" :aria-label="`حذف الصفحة ${page.title}`" @click="deletePage(page.id)" class="text-red-400 hover:text-red-300"><Trash2 class="w-4 h-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div v-show="activeTab === 'header'" class="animate-fade-in pb-20"> 
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-blue-400"><Palette class="w-5 h-5" /> ألوان وتصميم الهيدر</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-sm text-gray-400 mb-2">لون الخلفية</label><div class="flex gap-2"><input v-model="form.header_bg_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.header_bg_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div>
                    <div><label class="block text-sm text-gray-400 mb-2">لون النصوص والروابط</label><div class="flex gap-2"><input v-model="form.header_text_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.header_text_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div>
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-cyan-400"><Sliders class="w-5 h-5" /> خصائص الهيدر المتقدمة</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm text-gray-400 mb-2">لون Hover للروابط</label><div class="flex gap-2"><input v-model="form.header_link_hover_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.header_link_hover_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div>
                    <div><label class="block text-sm text-gray-400 mb-2">ارتفاع الهيدر (px)</label><input v-model.number="form.header_height" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">حجم اسم الموقع (px)</label><input v-model.number="form.header_title_size" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">حجم روابط القائمة (px)</label><input v-model.number="form.header_menu_size" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">Padding أفقي للهيدر (px)</label><input v-model.number="form.header_padding_x" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">استدارة زر CTA (px)</label><input v-model.number="form.header_cta_radius" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">Blur الخلفية (px)</label><input v-model.number="form.header_blur" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">وضوح حدود الهيدر (%)</label><input v-model.number="form.header_border_opacity" type="number" min="0" max="100" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-pink-400"><Share2 class="w-5 h-5" /> روابط التواصل الاجتماعي</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input v-model="form.header_facebook" placeholder="رابط فيسبوك" class="bg-gray-800 border-gray-700 rounded-lg p-3 text-white text-sm">
                    <input v-model="form.header_twitter" placeholder="رابط تويتر" class="bg-gray-800 border-gray-700 rounded-lg p-3 text-white text-sm">
                    <input v-model="form.header_instagram" placeholder="رابط انستقرام" class="bg-gray-800 border-gray-700 rounded-lg p-3 text-white text-sm">
                    <input v-model="form.header_linkedin" placeholder="رابط لينكد إن" class="bg-gray-800 border-gray-700 rounded-lg p-3 text-white text-sm">
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-green-400"><ExternalLink class="w-5 h-5" /> زر التواصل في الهيدر</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input v-model="form.header_contact_text" placeholder="نص الزر (تواصل معنا)" class="bg-gray-800 border-gray-700 rounded-lg p-3 text-white text-sm">
                    <input v-model="form.header_contact_url" placeholder="رابط الزر" class="bg-gray-800 border-gray-700 rounded-lg p-3 text-white text-sm" dir="ltr">
                </div>
                <div class="mt-4 flex justify-end"><button @click="submitSettings" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold transition flex items-center gap-2"><Save class="w-4 h-4" /> حفظ تعديلات الهيدر</button></div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-emerald-400"><Image class="w-5 h-5" /> إعدادات شعار الهوية داخل الهيدر</h3>
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <div class="space-y-5">
                        <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 cursor-pointer">
                            <span class="text-sm font-bold text-white">إظهار الشعار المحفوظ في الهوية العامة داخل الهيدر</span>
                            <input v-model="form.header_show_logo" type="checkbox" class="h-5 w-5 rounded border-gray-600 bg-gray-800 text-emerald-500 focus:ring-emerald-400">
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">موضع العلامة التجارية</label>
                                <select v-model="form.header_brand_position" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white">
                                    <option value="right">يمين</option>
                                    <option value="center">منتصف</option>
                                    <option value="left">يسار</option>
                                </select>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <p class="text-xs text-gray-400">المصدر</p>
                                <p class="mt-1 text-sm font-bold text-white">الهيدر سيقرأ الشعار مباشرة من إعدادات الهوية العامة بدون رفع نسخة ثانية.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <div><label class="block text-sm text-gray-400 mb-2">عرض الشعار (px)</label><input v-model.number="form.header_logo_width" type="number" min="24" max="320" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                            <div><label class="block text-sm text-gray-400 mb-2">ارتفاع الشعار (px)</label><input v-model.number="form.header_logo_height" type="number" min="24" max="240" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                            <div><label class="block text-sm text-gray-400 mb-2">Margin خارجي أفقي (px)</label><input v-model.number="form.header_logo_margin_inline" type="number" min="0" max="120" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                            <div><label class="block text-sm text-gray-400 mb-2">Margin خارجي رأسي (px)</label><input v-model.number="form.header_logo_margin_block" type="number" min="0" max="120" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                            <div><label class="block text-sm text-gray-400 mb-2">Padding داخلي أفقي (px)</label><input v-model.number="form.header_logo_padding_inline" type="number" min="0" max="80" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                            <div><label class="block text-sm text-gray-400 mb-2">Padding داخلي رأسي (px)</label><input v-model.number="form.header_logo_padding_block" type="number" min="0" max="80" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-slate-950/70 p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-300">Preview</p>
                        <div class="mt-4 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                            <div class="flex items-center justify-between gap-4 px-4 py-4" :style="{ backgroundColor: form.header_bg_color, color: form.header_text_color }">
                                <div class="flex items-center gap-2 opacity-70">
                                    <span class="h-2 w-8 rounded-full bg-white/15"></span>
                                    <span class="h-2 w-8 rounded-full bg-white/15"></span>
                                    <span class="h-2 w-8 rounded-full bg-white/15"></span>
                                </div>
                                <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
                                    <div
                                        v-if="form.header_show_logo"
                                        class="overflow-hidden rounded-xl bg-white/10"
                                        :style="{ width: `${form.header_logo_width || 40}px`, height: `${form.header_logo_height || 40}px`, paddingInline: `${form.header_logo_padding_inline || 0}px`, paddingBlock: `${form.header_logo_padding_block || 0}px`, marginInline: `${form.header_logo_margin_inline || 0}px`, marginBlock: `${form.header_logo_margin_block || 0}px` }"
                                    >
                                        <img v-if="form.site_logo" :src="getFileUrl(form.site_logo)" class="h-full w-full object-contain" alt="معاينة الشعار">
                                    </div>
                                    <span class="text-sm font-bold text-white/90">{{ form.site_name || 'اسم الموقع' }}</span>
                                </div>
                                <div class="h-9 w-24 rounded-xl bg-white/10"></div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs leading-6 text-gray-400">نثبت تحكم الشعار على سطح المكتب بشكل كامل، مع إبقاء نسخة الجوال مستقرة وواضحة للاستخدام اليومي.</p>
                    </div>
                </div>
            </div>

            <hr class="border-white/10 my-8" />

            <h3 class="text-lg font-bold mb-4 text-white">إدارة روابط القائمة</h3>
            <div class="bg-white/5 p-4 rounded-xl mb-8 flex flex-col md:flex-row gap-4 items-end border border-white/10"> 
                <div class="flex-1 w-full"><label class="block text-gray-400 text-sm mb-1">اسم القائمة</label><input v-model="headerMenuForm.title" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white"></div> 
                <div class="flex-1 w-full"><label class="block text-gray-400 text-sm mb-1">رابط</label><input v-model="headerMenuForm.url" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white" dir="ltr"></div> 
                <button @click="submitHeaderMenu" class="bg-blue-600 hover:bg-blue-700 px-6 py-2.5 rounded-lg text-white font-bold"><Plus class="w-4 h-4" /> إضافة</button> 
            </div> 
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"> 
                <div v-for="menu in headerMenus" :key="menu.id" class="bg-gray-800/50 border border-gray-700 rounded-xl p-4 flex flex-col h-full"> 
                    <div class="flex justify-between items-center mb-4 border-b border-gray-700 pb-3"><h3 class="font-bold text-lg text-white">{{ menu.title }}</h3><button type="button" :aria-label="`حذف قائمة الهيدر ${menu.title}`" @click="deleteHeaderMenu(menu.id)" class="text-red-400"><Trash2 class="w-4 h-4"/></button></div> 
                    <ul class="space-y-2 mb-4 flex-1"> 
                        <li v-for="item in menu.items" :key="item.id" class="flex justify-between items-center gap-3 bg-gray-900 p-3 rounded-lg text-sm border border-gray-700/50"><div class="flex min-w-0 items-center gap-2 truncate"><LinkIcon class="w-3 h-3 shrink-0 text-gray-500"/><span class="truncate text-gray-300">{{ item.label }}</span></div><button type="button" :aria-label="`حذف رابط الهيدر ${item.label}`" @click="deleteHeaderItem(item.id)" class="inline-flex shrink-0 items-center justify-center rounded-md p-2 text-red-400 transition hover:bg-red-500/10 hover:text-red-300"><Trash2 class="w-3 h-3"/></button></li> 
                    </ul> 
                    <button @click="openHeaderItemModal(menu.id)" class="w-full border border-dashed border-gray-600 text-gray-400 py-2.5 rounded-lg hover:bg-white/5 text-sm transition mt-auto flex items-center justify-center gap-2"><Plus class="w-4 h-4"/> إضافة عنصر</button> 
                </div> 
            </div> 
        </div>

        <div v-show="activeTab === 'footer'" class="animate-fade-in pb-20"> 
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8"> 
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-yellow-400"><Palette class="w-5 h-5" /> ألوان وتصميم الفوتر</h3> 
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm text-gray-400 mb-2">لون الخلفية</label><div class="flex gap-2"><input v-model="form.footer_bg_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.footer_bg_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div>
                    <div><label class="block text-sm text-gray-400 mb-2">لون العناوين</label><div class="flex gap-2"><input v-model="form.footer_heading_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.footer_heading_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div>
                    <div><label class="block text-sm text-gray-400 mb-2">لون النصوص</label><div class="flex gap-2"><input v-model="form.footer_text_color" type="color" class="h-10 w-16 bg-transparent border-0 cursor-pointer rounded"><input v-model="form.footer_text_color" type="text" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 text-white"></div></div>
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-cyan-400"><Sliders class="w-5 h-5" /> خصائص الفوتر المتقدمة</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm text-gray-400 mb-2">Padding علوي (px)</label><input v-model.number="form.footer_padding_top" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">Padding سفلي (px)</label><input v-model.number="form.footer_padding_bottom" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">المسافة بين الأعمدة (px)</label><input v-model.number="form.footer_columns_gap" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">حجم عنوان العمود (px)</label><input v-model.number="form.footer_title_size" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">حجم النصوص (px)</label><input v-model.number="form.footer_text_size" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div><label class="block text-sm text-gray-400 mb-2">حجم الروابط (px)</label><input v-model.number="form.footer_link_size" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">محاذاة محتوى الفوتر</label>
                        <select v-model="form.footer_align" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white">
                            <option value="right">يمين</option>
                            <option value="center">وسط</option>
                            <option value="left">يسار</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8 relative"> 
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2 text-yellow-400"><FileText class="w-5 h-5" /> نصوص الفوتر</h3> 
                <div class="grid grid-cols-1 gap-6"> 
                    <div><label class="block text-sm text-gray-400 mb-1">وصف الموقع</label><textarea v-model="form.footer_desc" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-white h-20"></textarea></div> 
                    <div><label class="block text-sm text-gray-400 mb-1">نص حقوق النشر</label><input v-model="form.footer_text" class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-white"></div> 
                </div> 
                <div class="mt-4 flex justify-end"><button @click="submitSettings" :disabled="form.processing" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg font-bold"><Save class="w-4 h-4" /> حفظ</button></div> 
            </div> 
            <div class="bg-white/5 p-4 rounded-xl mb-8 flex flex-col md:flex-row gap-4 items-end border border-white/10"> 
                <div class="flex-1 w-full"><label class="block text-gray-400 text-sm mb-1">عنوان العمود</label><input v-model="colForm.title" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white"></div> 
                <button @click="submitCol" class="bg-blue-600 hover:bg-blue-700 px-6 py-2.5 rounded-lg text-white font-bold"><Plus class="w-4 h-4" /> إضافة</button> 
            </div> 
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"> 
                <div v-for="col in footerColumns" :key="col.id" class="bg-gray-800/50 border border-gray-700 rounded-xl p-4 flex flex-col h-full"> 
                    <div class="flex justify-between items-center mb-4 border-b border-gray-700 pb-3"><h3 class="font-bold text-lg flex items-center gap-2 text-white">{{ col.title }}</h3><button type="button" :aria-label="`حذف عمود الفوتر ${col.title}`" @click="deleteCol(col.id)" class="text-red-400"><Trash2 class="w-4 h-4"/></button></div> 
                    <ul class="space-y-2 mb-4 flex-1"><li v-for="item in col.items" :key="item.id" class="flex justify-between items-center gap-3 bg-gray-900 p-3 rounded-lg text-sm border border-gray-700/50"><div class="flex min-w-0 items-center gap-2 truncate"><LinkIcon class="w-3 h-3 shrink-0 text-gray-500"/><span class="truncate text-gray-300">{{ item.label }}</span></div><button type="button" :aria-label="`حذف رابط الفوتر ${item.label}`" @click="deleteFooterItem(item.id)" class="inline-flex shrink-0 items-center justify-center rounded-md p-2 text-red-400 transition hover:bg-red-500/10 hover:text-red-300"><Trash2 class="w-3 h-3"/></button></li></ul> 
                    <button @click="openFooterItemModal(col.id)" class="w-full border border-dashed border-gray-600 text-gray-400 py-2.5 rounded-lg hover:bg-white/5 text-sm transition mt-auto flex items-center justify-center gap-2"><Plus class="w-4 h-4"/> إضافة رابط</button> 
                </div> 
            </div> 
        </div>

        <div v-if="showPageModal" class="ui-theme-modal-backdrop fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-4"><div class="ui-theme-modal-panel flex max-h-[calc(100dvh-1.5rem)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-gray-900 shadow-2xl sm:max-h-[90vh]"><div class="flex items-center justify-between border-b border-gray-800 px-4 py-4 sm:px-6"><h3 class="text-base font-bold text-white sm:text-lg">{{ isEditingPage ? 'تعديل الصفحة' : 'صفحة جديدة' }}</h3><button type="button" @click="showPageModal = false" class="rounded-lg p-2 text-gray-400 transition hover:bg-white/5 hover:text-white" aria-label="إغلاق نموذج الصفحة"><X class="h-5 w-5" /></button></div><div class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6"><input v-model="pageForm.title" class="w-full rounded-lg border border-gray-700 bg-gray-800 p-3 text-white" placeholder="العنوان"><input v-if="isEditingPage" v-model="pageForm.slug" class="w-full rounded-lg border border-gray-700 bg-gray-800 p-3 text-white" placeholder="Slug"><textarea v-model="pageForm.content" class="h-72 w-full rounded-lg border border-gray-700 bg-gray-800 p-3 font-mono text-white sm:h-96" placeholder="المحتوى"></textarea></div><div class="flex flex-col gap-3 border-t border-gray-800 bg-gray-900 px-4 py-4 sm:flex-row sm:px-6"><button type="button" @click="showPageModal = false" class="w-full rounded-xl bg-gray-800 py-3 text-white transition hover:bg-gray-700 sm:flex-1">إلغاء</button><button type="button" @click="submitPage" class="w-full rounded-xl bg-green-600 py-3 font-bold text-white transition hover:bg-green-700 sm:flex-1">حفظ</button></div></div></div>

        <div v-if="showCompModal" class="ui-theme-modal-backdrop fixed inset-0 z-[70] flex items-center justify-center p-4 font-cairo backdrop-blur-sm">
            <div class="ui-theme-modal-panel flex min-h-[70dvh] max-h-[calc(100dvh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-700 bg-gray-900 shadow-2xl sm:min-h-0 sm:max-h-[90vh]">
                <div class="flex items-center justify-between border-b border-gray-800 px-4 py-4 sm:px-6">
                    <h3 class="flex items-center gap-2 text-base font-bold text-white sm:text-xl">إدارة المكون: <span class="text-blue-400 uppercase">{{ compForm.type }}</span></h3>
                    <button type="button" @click="showCompModal = false" class="rounded-lg p-2 text-gray-400 transition hover:bg-white/5 hover:text-white" aria-label="إغلاق نموذج المكوّن"><X class="w-6 h-6"/></button>
                </div>
                
                <div class="flex-1 space-y-8 overflow-y-auto p-4 sm:p-6">
                    <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block text-xs text-gray-400 mb-1">الاسم التعريفي</label><input v-model="compForm.name" class="w-full bg-gray-900 border-gray-600 rounded-lg text-white p-2.5"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">الكود المختصر</label><input v-model="compForm.shortcode" class="w-full bg-gray-900 border-gray-600 rounded-lg text-yellow-500 font-mono p-2.5 text-left" dir="ltr"></div>
                        </div>
                    </div>

                    <div v-if="canUseDesignControls" class="bg-gray-800/50 p-4 rounded-xl border border-gray-700 space-y-5">
                        <h4 class="text-xs font-bold text-cyan-400 uppercase tracking-wider">Design Controls</h4>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div><label class="block text-xs text-gray-400 mb-1">هامش علوي (px)</label><input type="number" v-model.number="compForm.designData.marginTop" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">هامش سفلي (px)</label><input type="number" v-model.number="compForm.designData.marginBottom" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">حشو علوي (px)</label><input type="number" v-model.number="compForm.designData.paddingTop" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">حشو سفلي (px)</label><input type="number" v-model.number="compForm.designData.paddingBottom" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">نوع الخلفية</label>
                                <select v-model="compForm.designData.backgroundType" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm">
                                    <option value="none">بدون</option>
                                    <option value="color">لون</option>
                                    <option value="gradient">تدرج</option>
                                    <option value="image">صورة</option>
                                </select>
                            </div>
                            <div v-if="compForm.designData.backgroundType === 'color'">
                                <label class="block text-xs text-gray-400 mb-1">لون الخلفية</label>
                                <input type="color" v-model="compForm.designData.backgroundColor" class="w-full h-9 rounded cursor-pointer border-0 bg-transparent p-0">
                            </div>
                            <div v-if="compForm.designData.backgroundType === 'gradient'" class="md:col-span-2">
                                <label class="block text-xs text-gray-400 mb-1">تدرج CSS</label>
                                <input v-model="compForm.designData.backgroundGradient" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm" dir="ltr">
                            </div>
                            <div v-if="compForm.designData.backgroundType === 'image'" class="md:col-span-2">
                                <label class="block text-xs text-gray-400 mb-1">صورة الخلفية</label>
                                <div class="flex gap-2">
                                    <input v-model="compForm.designData.backgroundImage" class="flex-1 bg-gray-900 border-gray-600 rounded p-2 text-white text-xs" dir="ltr">
                                    <button type="button" @click="openMediaManager('design_background_image', 'image', 'component')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 rounded text-xs">اختر</button>
                                </div>
                            </div>
                            <div v-if="compForm.designData.backgroundType === 'image'">
                                <label class="block text-xs text-gray-400 mb-1">وضوح الخلفية ({{ compForm.designData.backgroundOpacity }}%)</label>
                                <input type="range" min="0" max="100" v-model.number="compForm.designData.backgroundOpacity" class="w-full accent-cyan-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div><label class="block text-xs text-gray-400 mb-1">المحاذاة</label><select v-model="compForm.designData.textAlign" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="text-right">يمين</option><option value="text-center">وسط</option><option value="text-left">يسار</option></select></div>
                            <div><label class="block text-xs text-gray-400 mb-1">حجم العنوان</label><input type="number" v-model.number="compForm.designData.titleSize" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">حجم العنوان الفرعي</label><input type="number" v-model.number="compForm.designData.subtitleSize" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">حجم النص</label><input type="number" v-model.number="compForm.designData.bodySize" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                        </div>

                        <div v-if="componentUsesImage" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <div><label class="block text-xs text-gray-400 mb-1">عرض الصورة (%)</label><input type="number" v-model.number="compForm.designData.imageWidth" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">ارتفاع الصورة (px)</label><input type="number" v-model.number="compForm.designData.imageHeight" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-400 mb-1">طريقة العرض</label><select v-model="compForm.designData.imageFit" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="cover">Cover</option><option value="contain">Contain</option><option value="fill">Fill</option><option value="scale-down">Scale Down</option></select></div>
                            <div><label class="block text-xs text-gray-400 mb-1">اتجاه الصورة</label><select v-model="compForm.designData.imagePosition" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="center center">Center</option><option value="top center">Top</option><option value="bottom center">Bottom</option><option value="center right">Right</option><option value="center left">Left</option></select></div>
                            <div><label class="block text-xs text-gray-400 mb-1">حدود الصورة (px)</label><input type="number" v-model.number="compForm.designData.imageRadius" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                        </div>
                    </div>
                    <div v-if="compForm.type === 'banner'" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div><label class="block text-xs text-gray-400 mb-1">العنوان الرئيسي</label><input v-model="compForm.bannerData.title" class="w-full bg-gray-800 border-gray-600 rounded p-2.5 text-white"></div>
                                <div><label class="block text-xs text-gray-400 mb-1">العنوان الفرعي</label><textarea v-model="compForm.bannerData.subtitle" class="w-full bg-gray-800 border-gray-600 rounded p-2.5 text-white h-24"></textarea></div>
                            </div>
                            <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700 space-y-4">
                                <h4 class="text-xs font-bold text-blue-400 uppercase mb-2">تنسيق المربع الزجاجي</h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">لون المربع</label>
                                        <div class="flex gap-2">
                                            <input type="color" v-model="compForm.bannerData.glassBgColor" class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent p-0">
                                            <input type="text" v-model="compForm.bannerData.glassBgColor" class="flex-1 bg-gray-900 border-gray-600 rounded text-[10px] text-white p-1">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">درجة الشفافية ({{compForm.bannerData.glassOpacity}}%)</label>
                                        <input type="range" v-model="compForm.bannerData.glassOpacity" min="0" max="100" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">درجة الضبابية ({{ compForm.bannerData.glassBlur }}px)</label>
                                    <input type="range" v-model.number="compForm.bannerData.glassBlur" min="0" max="40" step="1" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">ارتفاع الصندوق (px)</label>
                                    <input type="number" v-model.number="compForm.bannerData.glassHeight" min="120" max="900" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">هامش علوي (px)</label>
                                    <input type="number" v-model.number="compForm.bannerData.glassMarginTop" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs">
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">هامش سفلي (px)</label>
                                        <input type="number" v-model.number="compForm.bannerData.glassMarginBottom" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">هامش يمين (px)</label>
                                        <input type="number" v-model.number="compForm.bannerData.glassMarginRight" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">هامش يسار (px)</label>
                                    <input type="number" v-model.number="compForm.bannerData.glassMarginLeft" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs">
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div><label class="block text-xs text-gray-500 mb-1">المحاذاة</label><select v-model="compForm.bannerData.alignment" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs"><option value="text-center">وسط</option><option value="text-right">يمين</option><option value="text-left">يسار</option></select></div>
                                    <div><label class="block text-xs text-gray-500 mb-1">الارتفاع</label><select v-model="compForm.bannerData.height" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs"><option value="min-h-[500px]">500px</option><option value="min-h-[600px]">600px</option><option value="min-h-screen">كامل الشاشة</option></select></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700">
                            <h4 class="text-xs font-bold text-gray-300 uppercase mb-3 flex items-center gap-2"><MousePointer class="w-3 h-3"/> إعدادات النصوص والزر</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div><label class="block text-xs text-gray-500 mb-1">لون العنوان</label><input type="color" v-model="compForm.bannerData.titleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                                <div><label class="block text-xs text-gray-500 mb-1">لون الوصف</label><input type="color" v-model="compForm.bannerData.subtitleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                                <div><label class="block text-xs text-gray-500 mb-1">لون الزر</label><input type="color" v-model="compForm.bannerData.btnBgColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                                <div><label class="block text-xs text-gray-500 mb-1">نص الزر</label><input type="color" v-model="compForm.bannerData.btnTextColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <input v-model="compForm.bannerData.btnText" placeholder="نص الزر" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm">
                                <input v-model="compForm.bannerData.btnUrl" placeholder="رابط الزر" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm" dir="ltr">
                            </div>
                        </div>
                        <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700 flex flex-col md:flex-row gap-4 items-center">
                            <div class="flex-1 w-full"><label class="block text-xs text-gray-400 mb-1">نوع الخلفية</label><select v-model="compForm.bannerData.mediaType" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="image">صورة</option><option value="video">فيديو</option></select></div>
                            <div class="flex-1 w-full"><label class="block text-xs text-gray-400 mb-1">الشفافية</label><select v-model="compForm.bannerData.overlay" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="bg-black/30">خفيفة</option><option value="bg-black/50">متوسطة</option><option value="bg-black/70">داكنة</option></select></div>
                            <button @click="openMediaManager('banner_media', compForm.bannerData.mediaType, 'component')" type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold flex items-center gap-2 mt-4 md:mt-0"><UploadCloud class="w-4 h-4"/> اختر الملف</button>
                        </div>
                    </div>

                    <div v-if="compForm.type === 'info_section'" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <input v-model="compForm.infoData.title" class="w-full bg-gray-800 border-gray-600 rounded p-2.5 text-white" placeholder="العنوان">
                                <textarea v-model="compForm.infoData.text" class="w-full bg-gray-800 border-gray-600 rounded p-2.5 text-white h-32" placeholder="النص"></textarea>
                            </div>
                            <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700 space-y-4">
                                <h4 class="text-xs font-bold text-gray-300 uppercase mb-2">تخصيص</h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div><label class="block text-xs text-gray-500 mb-1">لون العنوان</label><div class="flex gap-2"><input type="color" v-model="compForm.infoData.titleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div></div>
                                    <div><label class="block text-xs text-gray-500 mb-1">لون النص</label><div class="flex gap-2"><input type="color" v-model="compForm.infoData.textColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div></div>
                                    <div><label class="block text-xs text-gray-500 mb-1">اتجاه الصورة</label><select v-model="compForm.infoData.layout" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs"><option value="image_right">يمين</option><option value="image_left">يسار</option></select></div>
                                    <div><label class="block text-xs text-gray-500 mb-1">لون الخلفية</label><select v-model="compForm.infoData.bgColor" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs"><option value="transparent">شفاف</option><option value="bg-gray-800">رمادي</option><option value="bg-blue-900/10">أزرق فاتح</option></select></div>
                                </div>
                                <div class="pt-2 border-t border-gray-700">
                                    <label class="block text-xs text-gray-500 mb-1">الصورة</label>
                                    <div class="space-y-3">
                                        <div class="overflow-hidden rounded-xl border border-gray-700 bg-gray-900/70">
                                            <div v-if="compForm.infoData.image" class="aspect-[16/10]">
                                                <img :src="getFileUrl(compForm.infoData.image)" alt="معاينة صورة القسم" class="h-full w-full object-cover">
                                            </div>
                                            <div v-else class="flex min-h-32 items-center justify-center px-4 py-6 text-center text-xs text-gray-500">
                                                لم يتم اختيار صورة بعد
                                            </div>
                                        </div>
                                        <input v-model="compForm.infoData.image" readonly class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-[11px] text-gray-300" dir="ltr" placeholder="مسار الصورة المختارة">
                                        <div class="grid grid-cols-2 gap-2">
                                            <button @click="openMediaManager('info_image', 'image', 'component')" type="button" class="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 rounded text-xs">اختيار الصورة</button>
                                            <button type="button" class="w-full bg-gray-900 hover:bg-gray-800 text-gray-300 py-2 rounded text-xs border border-gray-700 disabled:opacity-50" :disabled="!compForm.infoData.image" @click="compForm.infoData.image = ''">إزالة الصورة</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div class="md:col-span-2"><label class="block text-xs text-gray-500 mb-1">نص الزر</label><input v-model="compForm.infoData.btnText" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">لون الزر</label><input type="color" v-model="compForm.infoData.btnBgColor" class="w-full h-9 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">لون النص</label><input type="color" v-model="compForm.infoData.btnTextColor" class="w-full h-9 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                        </div>
                    </div>

                    <div v-if="compForm.type === 'stats'" class="space-y-6">
                        <div class="flex gap-4">
                            <input v-model="compForm.statsData.title" placeholder="عنوان القسم" class="flex-1 bg-gray-800 border-gray-600 rounded p-2.5 text-white">
                            <div class="flex items-center gap-2 bg-gray-800 p-2 rounded border border-gray-600"><label class="text-xs text-gray-400">لون العنوان:</label><input type="color" v-model="compForm.statsData.titleColor" class="w-6 h-6 border-0 bg-transparent p-0 cursor-pointer"></div>
                        </div>
                        <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700 grid grid-cols-3 gap-4">
                            <div><label class="block text-xs text-gray-500 mb-1">لون الكروت</label><select v-model="compForm.statsData.cardBgColor" class="w-full bg-gray-900 border-gray-600 rounded p-1.5 text-white text-xs"><option value="bg-gray-800">رمادي داكن</option><option value="bg-gray-900">أسود</option><option value="bg-blue-900/20">أزرق شفاف</option></select></div>
                            <div><label class="block text-xs text-gray-500 mb-1">لون الأرقام</label><div class="flex gap-2"><input type="color" v-model="compForm.statsData.numberColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div></div>
                            <div><label class="block text-xs text-gray-500 mb-1">لون النصوص</label><div class="flex gap-2"><input type="color" v-model="compForm.statsData.labelColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div></div>
                        </div>
                        <div v-for="(stat, i) in compForm.statsData.stats" :key="i" class="flex gap-2 items-center bg-gray-800 p-2 rounded border border-gray-700">
                            <input v-model="stat.number" placeholder="الرقم" class="w-24 bg-gray-900 border-gray-600 rounded p-2 text-white text-sm">
                            <input v-model="stat.suffix" placeholder="الرمز" class="w-16 bg-gray-900 border-gray-600 rounded p-2 text-white text-sm">
                            <input v-model="stat.label" placeholder="الوصف" class="flex-1 bg-gray-900 border-gray-600 rounded p-2 text-white text-sm">
                            <button type="button" :aria-label="`إزالة الإحصائية رقم ${i + 1}`" @click="removeStat(i)" class="text-red-400 p-2 hover:bg-gray-700 rounded"><Trash2 class="w-4 h-4"/></button>
                        </div>
                        <button @click="addStat" class="text-blue-400 text-sm font-bold flex items-center gap-1">+ إضافة إحصائية</button>
                    </div>

                    <div v-if="compForm.type === 'pricing'" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input v-model="compForm.pricingData.title" placeholder="العنوان الرئيسي" class="w-full bg-gray-800 border-gray-600 rounded p-2.5 text-white">
                            <input v-model="compForm.pricingData.subtitle" placeholder="الوصف القصير" class="w-full bg-gray-800 border-gray-600 rounded p-2.5 text-white">
                        </div>
                        <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700">
                            <label class="block text-xs text-gray-500 mb-2">بداية ظهور الباقات</label>
                            <select v-model="compForm.pricingData.plans_flow" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-sm text-white">
                                <option value="right">ابدأ من اليمين</option>
                                <option value="left">ابدأ من اليسار</option>
                                <option value="center">ابدأ من المنتصف</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-700 bg-gray-800/50 p-4 sm:grid-cols-3">
                            <div class="flex-1"><label class="block text-xs text-gray-500 mb-1">لون العنوان</label><input type="color" v-model="compForm.pricingData.titleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                            <div class="flex-1"><label class="block text-xs text-gray-500 mb-1">لون الوصف</label><input type="color" v-model="compForm.pricingData.subtitleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                            <div class="flex-1"><label class="block text-xs text-gray-500 mb-1">لون السعر</label><input type="color" v-model="compForm.pricingData.priceColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                        </div>
                        <div v-for="(plan, i) in compForm.pricingData.plans" :key="i" class="bg-gray-800 p-4 rounded-xl border border-gray-700 relative">
                            <button type="button" :aria-label="`إزالة الباقة ${plan.name || i + 1}`" @click="removePlan(i)" class="absolute top-2 left-2 text-red-400 p-1 hover:bg-gray-700 rounded"><Trash2 class="w-4 h-4"/></button>
                            <div class="mb-1 grid grid-cols-1 gap-2 text-[11px] font-semibold text-gray-400 sm:grid-cols-3">
                                <span>حالة الباقة</span>
                                <span>اسم الباقة</span>
                                <span>السعر المعروض في البطاقة</span>
                            </div>
                            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                                <label class="inline-flex items-center text-xs text-blue-400 bg-blue-900/20 px-2 py-1 rounded cursor-pointer"><input type="checkbox" v-model="plan.isFeatured" class="mr-1 rounded bg-gray-700 border-gray-600 text-blue-600"> باقة مميزة</label>
                                <input v-model="plan.name" placeholder="اسم الباقة" class="flex-1 bg-gray-900 border-gray-600 rounded p-2 text-white text-sm">
                                <input v-model="plan.price" placeholder="السعر" class="w-24 bg-gray-900 border-gray-600 rounded p-2 text-white text-sm font-bold">
                            </div>
                            <div class="mb-1 grid grid-cols-1 gap-2 text-[11px] font-semibold text-gray-400 md:grid-cols-4">
                                <span>السعر الشهري</span>
                                <span>السعر السنوي</span>
                                <span>عدد المستخدمين المضمنين</span>
                                <span>سعر المستخدم الإضافي شهريًا</span>
                            </div>
                            <div class="mb-3 grid grid-cols-1 md:grid-cols-4 gap-2">
                                <input v-model="plan.monthly_price" type="number" min="0" step="0.01" placeholder="السعر الشهري" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                <input v-model="plan.yearly_price" type="number" min="0" step="0.01" placeholder="السعر السنوي" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                <input v-model="plan.included_users_count" type="number" min="0" step="1" placeholder="المستخدمون المضمنون" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                <input v-model="plan.extra_user_monthly_price" type="number" min="0" step="0.01" placeholder="سعر المستخدم الإضافي شهريًا" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                            </div>
                            <div class="mb-1 grid grid-cols-1 gap-2 text-[11px] font-semibold text-gray-400 md:grid-cols-4">
                                <span>الدور المستهدف</span>
                                <span>دورية الفوترة الافتراضية</span>
                                <span>معرّف الخطة المرتبطة</span>
                                <span>رابط مخصص</span>
                            </div>
                            <div class="mb-3 grid grid-cols-1 md:grid-cols-4 gap-2">
                                <select v-model="plan.role_type" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                    <option value="SUPERVISOR">مشرف</option>
                                    <option value="SCHOOL_MANAGER">مدير مدرسة</option>
                                </select>
                                <select v-model="plan.billing_cycle" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                    <option value="MONTHLY">شهري افتراضي</option>
                                    <option value="YEARLY">سنوي افتراضي</option>
                                </select>
                                <input v-model="plan.plan_id" type="number" min="1" placeholder="Plan ID (optional)" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                <input v-model="plan.url" placeholder="Custom URL (optional)" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                            </div>
                            <div class="mb-1 grid grid-cols-1 gap-2 text-[11px] font-semibold text-gray-400 md:grid-cols-3">
                                <span>محاذاة عنوان الباقة</span>
                                <span>محاذاة السعر</span>
                                <span>محاذاة المميزات</span>
                            </div>
                            <div class="mb-3 grid grid-cols-1 md:grid-cols-3 gap-2">
                                <select v-model="plan.title_alignment" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                    <option value="right">محاذاة عنوان الباقة: يمين</option>
                                    <option value="center">محاذاة عنوان الباقة: وسط</option>
                                    <option value="left">محاذاة عنوان الباقة: يسار</option>
                                </select>
                                <select v-model="plan.price_alignment" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                    <option value="right">محاذاة السعر: يمين</option>
                                    <option value="center">محاذاة السعر: وسط</option>
                                    <option value="left">محاذاة السعر: يسار</option>
                                </select>
                                <select v-model="plan.features_alignment" class="bg-gray-900 border-gray-600 rounded p-2 text-xs text-white">
                                    <option value="right">محاذاة المميزات: يمين</option>
                                    <option value="center">محاذاة المميزات: وسط</option>
                                    <option value="left">محاذاة المميزات: يسار</option>
                                </select>
                            </div>
                            <p class="mb-3 text-[11px] text-gray-500">
                                اترك Custom URL فارغاً وسيتم التوجيه تلقائياً حسب نوع الباقة.
                            </p>
                            <div class="space-y-2 pl-4 border-l-2 border-gray-700">
                                <div v-for="(feat, f) in plan.features" :key="f" class="flex gap-2 items-center">
                                    <Check class="w-3 h-3 text-green-500" v-if="feat.included"/><X class="w-3 h-3 text-red-500" v-else/>
                                    <input v-model="feat.text" placeholder="ميزة" class="flex-1 bg-gray-900 border-gray-600 rounded p-1.5 text-xs text-white">
                                    <label class="cursor-pointer"><input type="checkbox" v-model="feat.included" class="hidden"><span :class="feat.included ? 'text-green-500' : 'text-gray-500'" class="text-xs font-bold px-2">✓</span></label>
                                    <button type="button" :aria-label="`إزالة الميزة رقم ${f + 1} من الباقة ${plan.name || i + 1}`" @click="removeFeature(i, f)" class="text-gray-500 hover:text-red-400"><X class="w-3 h-3"/></button>
                                </div>
                                <button @click="addFeature(i)" class="text-blue-400 text-xs font-bold">+ إضافة ميزة</button>
                            </div>
                        </div>
                        <button @click="addPlan" class="w-full py-2 border-2 border-dashed border-gray-600 text-gray-400 rounded-xl hover:border-blue-500 hover:text-white transition">+ إضافة باقة جديدة</button>
                    </div>

                    <div v-if="compForm.type === 'faq'" class="space-y-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <input v-model="compForm.faqData.title" placeholder="عنوان القسم" class="flex-1 bg-gray-800 border-gray-600 rounded p-2.5 text-white">
                            <div class="flex gap-2">
                                <div title="لون العنوان"><input type="color" v-model="compForm.faqData.titleColor" class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                                <div title="لون السؤال"><input type="color" v-model="compForm.faqData.questionColor" class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                                <div title="لون الإجابة"><input type="color" v-model="compForm.faqData.answerColor" class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div>
                            </div>
                        </div>
                        <div v-for="(item, i) in compForm.faqData.items" :key="i" class="bg-gray-800 p-4 rounded border border-gray-700">
                            <div class="flex justify-between mb-2"><span class="text-xs text-gray-400 font-bold">سؤال رقم {{i+1}}</span><button @click="removeFaq(i)" class="text-red-400"><Trash2 class="w-4 h-4"/></button></div>
                            <input v-model="item.question" placeholder="اكتب السؤال هنا" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white mb-2 text-sm font-bold">
                            <textarea v-model="item.answer" placeholder="اكتب الإجابة هنا" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm h-20"></textarea>
                        </div>
                        <button @click="addFaq" class="text-blue-400 text-sm font-bold">+ إضافة سؤال جديد</button>
                    </div>

                    <div v-if="compForm.type === 'section_title'" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input v-model="compForm.titleData.title" class="w-full bg-gray-800 border-gray-600 rounded p-3 text-white font-bold text-lg" placeholder="العنوان الرئيسي">
                            <input v-model="compForm.titleData.subtitle" class="w-full bg-gray-800 border-gray-600 rounded p-3 text-white" placeholder="العنوان الفرعي">
                        </div>
                        <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-700 bg-gray-800/50 p-4 sm:grid-cols-2">
                            <div><label class="block text-xs text-gray-500 mb-1">النمط</label><select v-model="compForm.titleData.style" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="gradient">تدرج (Modern)</option><option value="bold">عريض (Bold)</option><option value="minimal">بسيط (Minimal)</option></select></div>
                            <div><label class="block text-xs text-gray-500 mb-1">المحاذاة</label><select v-model="compForm.titleData.alignment" class="w-full bg-gray-900 border-gray-600 rounded p-2 text-white text-sm"><option value="text-center">وسط</option><option value="text-right">يمين</option><option value="text-left">يسار</option></select></div>
                            <div><label class="block text-xs text-gray-500 mb-1">لون العنوان</label><div class="flex gap-2"><input type="color" v-model="compForm.titleData.titleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div></div>
                            <div><label class="block text-xs text-gray-500 mb-1">لون الفرعي</label><div class="flex gap-2"><input type="color" v-model="compForm.titleData.subtitleColor" class="w-full h-8 rounded cursor-pointer border-0 bg-transparent p-0"></div></div>
                        </div>
                    </div>

                    <div v-if="compForm.type === 'html'"><textarea v-model="compForm.content" rows="10" class="w-full bg-gray-800 border-gray-600 rounded-xl text-white p-4 font-mono text-sm leading-relaxed" placeholder="<h1>محتوى HTML مباشر</h1>"></textarea></div>
                </div>

                <div class="flex flex-col gap-3 border-t border-gray-800 bg-gray-900 p-4 sm:flex-row sm:p-6">
                    <button type="button" @click="showCompModal = false" class="flex-1 rounded-xl border border-gray-700 bg-gray-800 py-3 font-bold text-white transition hover:bg-gray-700">إلغاء</button>
                    <button type="button" @click="submitComp" :disabled="compForm.processing" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-purple-600 py-3 font-bold text-white shadow-lg transition hover:bg-purple-700">
                        <Save class="w-4 h-4"/> حفظ المكون
                    </button>
                </div>
            </div>
        </div>
        
        <div v-if="showFooterItemModal" class="ui-theme-modal-backdrop fixed inset-0 flex items-center justify-center z-[60] p-4">
             <div class="ui-theme-modal-panel bg-gray-900 p-6 rounded-2xl w-full max-w-md border border-white/10 shadow-2xl"><h3 class="font-bold text-xl mb-6 text-white">إضافة رابط للفوتر</h3><div class="space-y-4"><div><label class="block text-sm text-gray-400 mb-1">نص الرابط</label><input v-model="footerItemForm.label" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white"></div><div><label class="block text-sm text-gray-400 mb-1">الرابط</label><input v-model="footerItemForm.url" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white" dir="ltr"></div></div><div class="flex gap-3 mt-8"><button @click="showFooterItemModal = false" class="flex-1 bg-gray-800 py-2.5 rounded-lg text-white">إلغاء</button><button @click="submitFooterItem" class="flex-1 bg-green-600 py-2.5 rounded-lg text-white font-bold">حفظ</button></div></div>
        </div>
        
        <div v-if="showHeaderItemModal" class="ui-theme-modal-backdrop fixed inset-0 flex items-center justify-center z-[60] p-4">
             <div class="ui-theme-modal-panel bg-gray-900 p-6 rounded-2xl w-full max-w-md border border-white/10 shadow-2xl"><h3 class="font-bold text-xl mb-6 text-white">إضافة عنصر فرعي</h3><div class="space-y-4"><div><label class="block text-sm text-gray-400 mb-1">نص العنصر</label><input v-model="headerItemForm.label" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white"></div><div><label class="block text-sm text-gray-400 mb-1">الرابط</label><input v-model="headerItemForm.url" class="w-full bg-gray-800 rounded-lg border-gray-700 text-white" dir="ltr"></div></div><div class="flex gap-3 mt-8"><button @click="showHeaderItemModal = false" class="flex-1 bg-gray-800 py-2.5 rounded-lg text-white">إلغاء</button><button @click="submitHeaderItem" class="flex-1 bg-green-600 py-2.5 rounded-lg text-white font-bold">حفظ</button></div></div>
        </div>
    </AdminLayout>
</template>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>


