<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, LayoutDashboard } from 'lucide-vue-next';
import ThemeModeSwitch from '@/Components/Layout/ThemeModeSwitch.vue';
</script>

<template>
    <Head title="لوحة التحكم" />

    <div class="ui-auth-shell">
        <div class="absolute left-4 top-4 z-20 sm:left-6 sm:top-6">
            <ThemeModeSwitch size="sm" :show-label="true" />
        </div>

        <div class="ui-auth-content max-w-2xl">
            <div class="ui-auth-card text-center">
                <div class="ui-auth-heading mb-6">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                        <LayoutDashboard class="h-6 w-6 text-cyan-300" />
                    </div>
                    <h1 class="ui-auth-title">لوحة التحكم</h1>
                    <p class="ui-auth-copy">تم تسجيل الدخول بنجاح. يمكنك الآن الانتقال إلى الملف الشخصي أو متابعة العمل من المسار المناسب.</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Link :href="route('profile.edit')" class="ui-primary-button">
                        <ArrowLeft class="h-4 w-4" />
                        <span>تعديل الملف الشخصي</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
