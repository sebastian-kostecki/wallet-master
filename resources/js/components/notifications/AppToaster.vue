<script setup lang="ts">
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, CircleAlert, Info } from 'lucide-vue-next';
import { computed, h, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast, Toaster } from 'vue-sonner';

type ToastKind = 'success' | 'error' | 'info' | 'warning';

type PagePropsWithToast = {
    toast?: {
        type: ToastKind;
        message: string;
        message_key?: string;
        title?: string;
        title_key?: string;
    };
};

const toastOptions = computed(() => ({
    unstyled: true,
    classes: {
        toast: cn(
            'pointer-events-auto mx-auto mb-4 flex w-full max-w-xl items-start gap-3 rounded-lg border py-3 pl-4 pr-2 shadow-lg',
            'border-border bg-background text-foreground',
        ),
        title: 'text-sm font-medium',
        description: 'text-sm text-muted-foreground',
        icon: 'mt-0.5 shrink-0',
        success: 'border-emerald-700 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-500 dark:text-emerald-950',
        error: 'border-destructive bg-destructive text-destructive-foreground',
        info: 'border-ring/30 bg-secondary text-secondary-foreground',
        warning: 'border-amber-600 bg-amber-500 text-amber-950 dark:border-amber-500 dark:bg-amber-500 dark:text-amber-950',
    },
    icons: {
        success: h(CheckCircle2, { class: 'h-5 w-5' }),
        error: h(CircleAlert, { class: 'h-5 w-5' }),
        info: h(Info, { class: 'h-5 w-5' }),
        warning: h(AlertTriangle, { class: 'h-5 w-5' }),
    },
}));

const page = usePage<{ props: PagePropsWithToast }>();
const { t } = useI18n();

watch(
    () => page.props.toast,
    (value) => {
        if (!value) {
            return;
        }

        const type = value.type ?? 'info';
        const title = value.title_key ? t(value.title_key) : value.title;
        const message = value.message_key ? t(value.message_key) : value.message;

        if (!message) {
            return;
        }

        if (title) {
            toast[type](title, { description: message });
            return;
        }

        toast[type](message);
    },
    { immediate: true },
);
</script>

<template>
    <Toaster position="bottom-center" :duration="3500" offset="16px" closeButton :toastOptions="toastOptions" />
</template>
