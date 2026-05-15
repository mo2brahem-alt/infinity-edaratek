<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X, Facebook, Twitter, Instagram, Linkedin } from 'lucide-vue-next';
import { useProjectBranding } from '@/composables/useProjectBranding';

const isMenuOpen = ref(false);
const page = usePage();
const settings = computed(() => page.props.app_settings || {});
const menus = computed(() => page.props.headerMenus || []);
const { siteName, projectLogoUrl, showHeaderLogo, headerLogoStyle } = useProjectBranding();

// الألوان الديناميكية
const headerStyle = computed(() => ({
    backgroundColor: settings.value.header_bg_color || 'rgba(17, 24, 39, 0.95)',
    color: settings.value.header_text_color || '#d1d5db',
    borderColor: 'rgba(255, 255, 255, 0.1)'
}));

const textStyle = computed(() => ({
    color: settings.value.header_text_color || '#d1d5db'
}));
</script>

<template>
  <header 
    class="fixed top-0 w-full z-50 transition-all duration-300 backdrop-blur-md border-b"
    :style="headerStyle"
  >
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
      
      <Link href="/" class="flex items-center gap-3">
        <img
            v-if="showHeaderLogo"
            :src="projectLogoUrl"
            :alt="siteName"
            :style="headerLogoStyle"
            class="shrink-0 rounded-md object-contain"
        />
        <span v-else class="text-2xl font-bold tracking-wide" :style="{ color: settings.header_text_color || '#ffffff' }">
            {{ siteName }}
        </span>
      </Link>

      <nav class="hidden lg:flex items-center gap-8">
        <template v-for="menu in menus" :key="menu.id">
            <Link 
                v-if="!menu.items.length" 
                :href="menu.url" 
                class="transition text-sm font-medium hover:opacity-70"
                :style="textStyle"
            >
                {{ menu.title }}
            </Link>
            
            <div v-else class="relative group">
                <button 
                    class="transition text-sm font-medium flex items-center gap-1 hover:opacity-70"
                    :style="textStyle"
                >
                    {{ menu.title }}
                </button>
                <div class="absolute top-full right-0 mt-2 w-48 bg-gray-800 border border-gray-700 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform translate-y-2 group-hover:translate-y-0">
                    <Link 
                        v-for="item in menu.items" 
                        :key="item.id" 
                        :href="item.url" 
                        class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 hover:text-white first:rounded-t-xl last:rounded-b-xl"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </div>
        </template>
      </nav>

      <div class="hidden lg:flex items-center gap-5">
        
        <div class="flex items-center gap-3 border-l border-white/10 pl-5 ml-2">
            <a v-if="settings.header_facebook" :href="settings.header_facebook" target="_blank" rel="noopener noreferrer" class="hover:opacity-70 transition" :style="textStyle"><Facebook class="w-4 h-4"/></a>
            <a v-if="settings.header_twitter" :href="settings.header_twitter" target="_blank" rel="noopener noreferrer" class="hover:opacity-70 transition" :style="textStyle"><Twitter class="w-4 h-4"/></a>
            <a v-if="settings.header_instagram" :href="settings.header_instagram" target="_blank" rel="noopener noreferrer" class="hover:opacity-70 transition" :style="textStyle"><Instagram class="w-4 h-4"/></a>
            <a v-if="settings.header_linkedin" :href="settings.header_linkedin" target="_blank" rel="noopener noreferrer" class="hover:opacity-70 transition" :style="textStyle"><Linkedin class="w-4 h-4"/></a>
        </div>

        <div v-if="page.props.auth.user" class="flex items-center gap-3">
            <Link href="/dashboard" class="text-white bg-blue-600 hover:bg-blue-700 px-5 py-2 rounded-full text-sm font-bold transition shadow-lg shadow-blue-900/20">لوحة التحكم</Link>
        </div>
        <div v-else class="flex items-center gap-3">
            <Link href="/login" class="transition text-sm font-bold hover:opacity-70" :style="textStyle">دخول</Link>
            <a 
                v-if="settings.header_contact_url" 
                :href="settings.header_contact_url" 
                class="bg-white text-gray-900 hover:bg-gray-100 px-5 py-2 rounded-full transition duration-300 text-sm font-bold shadow-lg"
            >
                {{ settings.header_contact_text || 'تواصل معنا' }}
            </a>
        </div>

      </div>

      <button @click="isMenuOpen = !isMenuOpen" class="lg:hidden focus:outline-none" :style="textStyle">
        <Menu v-if="!isMenuOpen" class="w-7 h-7" />
        <X v-else class="w-7 h-7" />
      </button>
    </div>

    <div v-if="isMenuOpen" class="lg:hidden absolute w-full border-b shadow-2xl transition-all" :style="headerStyle">
      <div class="p-4 flex flex-col gap-4">
          <template v-for="menu in menus" :key="menu.id">
            <Link v-if="!menu.items.length" :href="menu.url" class="block py-2 text-center hover:bg-white/5 rounded-lg" :style="textStyle">{{ menu.title }}</Link>
            <div v-else class="text-center border-b border-gray-800 pb-2">
                <div class="text-xs text-gray-500 mb-2">{{ menu.title }}</div>
                <Link v-for="item in menu.items" :key="item.id" :href="item.url" class="block py-2 text-sm hover:opacity-70" :style="textStyle">{{ item.label }}</Link>
            </div>
          </template>
          
          <div class="flex justify-center gap-4 py-4 border-t border-gray-800">
            <a v-if="settings.header_facebook" :href="settings.header_facebook" :style="textStyle"><Facebook class="w-5 h-5"/></a>
            <a v-if="settings.header_twitter" :href="settings.header_twitter" :style="textStyle"><Twitter class="w-5 h-5"/></a>
            <a v-if="settings.header_instagram" :href="settings.header_instagram" :style="textStyle"><Instagram class="w-5 h-5"/></a>
          </div>

          <div class="flex flex-col gap-3">
              <Link href="/login" class="block text-center py-3 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition font-bold">تسجيل الدخول</Link>
              <a v-if="settings.header_contact_url" :href="settings.header_contact_url" class="block text-center py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-bold">
                {{ settings.header_contact_text }}
              </a>
          </div>
      </div>
    </div>
  </header>
</template>
