<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Globe, Phone, Mail, MapPin, Facebook, Twitter, Instagram, Linkedin } from 'lucide-vue-next';
import { useProjectBranding } from '@/composables/useProjectBranding';

const page = usePage();
const settings = computed(() => page.props.app_settings || {});
const { siteName, projectLogoUrl } = useProjectBranding();

// 2. تحديد الألوان كمتغيرات CSS
const cssVars = computed(() => {
    return {
        '--primary': settings.value.primary_color || '#2563eb',   // اللون الأساسي
        '--secondary': settings.value.secondary_color || '#1e1b4b', // اللون الثانوي
    };
});
</script>

<template>
    <div :style="cssVars" dir="rtl" class="min-h-screen bg-gray-900 font-cairo text-gray-100 flex flex-col">
        
        <nav class="border-b border-white/10 bg-gray-900/80 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20 items-center">
                    
                    <Link href="/" class="flex items-center gap-3 group">
                        <img :src="projectLogoUrl" :alt="siteName" class="w-10 h-10 object-contain group-hover:scale-110 transition" />
                        <span class="text-xl font-bold text-white tracking-wide">
                            {{ siteName }}
                        </span>
                    </Link>

                    <div class="flex items-center gap-4">
                        <Link :href="route('login')" class="text-gray-300 hover:text-white transition">دخول</Link>
                        <Link 
                            :href="route('register')" 
                            class="bg-custom-primary hover:opacity-90 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg"
                        >
                            ابدأ مجاناً
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-1 relative">
            <slot />
        </main>

        <footer class="bg-black/40 backdrop-blur-lg border-t border-white/5 pt-16 pb-8 relative z-10 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                    
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <img :src="projectLogoUrl" :alt="siteName" class="h-12 w-auto object-contain" />
                            <span class="text-xl font-bold text-gray-100">{{ siteName }}</span>
                        </div>
                        
                        <p class="text-gray-400 text-sm leading-relaxed mb-6">
                            نحن نقدم حلولاً تقنية مبتكرة لقطاع التعليم، نهدف لتبسيط الإدارة وتمكين المعلمين والطلاب بأحدث الأدوات الرقمية.
                        </p>
                        
                        <div class="flex gap-4">
                            <a href="#" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-custom-primary transition text-gray-400 hover:text-white"><Twitter class="w-5 h-5"/></a>
                            <a href="#" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-custom-primary transition text-gray-400 hover:text-white"><Facebook class="w-5 h-5"/></a>
                            <a href="#" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-custom-primary transition text-gray-400 hover:text-white"><Instagram class="w-5 h-5"/></a>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-white font-bold mb-6 text-lg relative inline-block">
                            روابط هامة
                            <span class="absolute -bottom-2 right-0 w-12 h-1 bg-custom-primary rounded-full"></span>
                        </h3>
                        <ul class="space-y-3 text-sm text-gray-400">
                            <li><Link href="/" class="hover:text-custom-primary transition flex items-center gap-2">الرئيسية</Link></li>
                            <li><a href="#" class="hover:text-custom-primary transition flex items-center gap-2">من نحن</a></li>
                            <li><a href="#" class="hover:text-custom-primary transition flex items-center gap-2">الأسعار والباقات</a></li>
                            <li><a href="#" class="hover:text-custom-primary transition flex items-center gap-2">سياسة الخصوصية</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-white font-bold mb-6 text-lg relative inline-block">
                            خدماتنا
                            <span class="absolute -bottom-2 right-0 w-12 h-1 bg-purple-500 rounded-full"></span>
                        </h3>
                        <ul class="space-y-3 text-sm text-gray-400">
                            <li><a href="#" class="hover:text-purple-400 transition">نظام إدارة المدارس</a></li>
                            <li><a href="#" class="hover:text-purple-400 transition">التعليم عن بعد (LMS)</a></li>
                            <li><a href="#" class="hover:text-purple-400 transition">تطبيقات أولياء الأمور</a></li>
                            <li><a href="#" class="hover:text-purple-400 transition">التقارير الذكية</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-white font-bold mb-6 text-lg relative inline-block">
                            تواصل معنا
                            <span class="absolute -bottom-2 right-0 w-12 h-1 bg-green-500 rounded-full"></span>
                        </h3>
                        <ul class="space-y-4 text-sm text-gray-400">
                            <li class="flex items-start gap-3">
                                <MapPin class="w-5 h-5 text-custom-primary shrink-0" />
                                <span>المملكة العربية السعودية، الرياض</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <Phone class="w-5 h-5 text-custom-primary shrink-0" />
                                <span dir="ltr">+966 50 123 4567</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <Mail class="w-5 h-5 text-custom-primary shrink-0" />
                                <span>info@edaratek.com</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-gray-500">
                    <p>{{ settings.footer_text || 'جميع الحقوق محفوظة © 2026 إدارتك.' }}</p>
                    <div class="flex gap-4">
                        <a href="#" class="hover:text-white transition">الشروط والأحكام</a>
                        <a href="#" class="hover:text-white transition">سياسة الاستخدام</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* كلاسات مخصصة تستخدم المتغيرات التي عرفناها في الأعلى */
.bg-custom-primary { background-color: var(--primary); }
.text-custom-primary { color: var(--primary); }
.hover\:bg-custom-primary:hover { background-color: var(--primary); }
.hover\:text-custom-primary:hover { color: var(--primary); }
</style>
