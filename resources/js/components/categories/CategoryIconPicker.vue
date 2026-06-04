<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { cn } from '@/lib/utils';
import { useI18n } from 'vue-i18n';

type IconOption = {
    value: string;
    label_key: string;
};

const props = defineProps<{
    icons: IconOption[];
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t, te } = useI18n();

function iconLabel(icon: IconOption): string {
    return te(icon.label_key) ? t(icon.label_key) : icon.value;
}
</script>

<template>
    <div class="grid max-h-52 grid-cols-6 gap-2 overflow-y-auto sm:grid-cols-8" role="listbox" aria-label="Category icon">
        <button
            v-for="icon in props.icons"
            :key="icon.value"
            type="button"
            role="option"
            :aria-selected="props.modelValue === icon.value"
            :aria-label="iconLabel(icon)"
            class="inline-flex h-10 w-10 items-center justify-center rounded-md border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :class="cn(props.modelValue === icon.value ? 'border-foreground bg-muted' : 'border-transparent hover:bg-muted/60')"
            @click="emit('update:modelValue', icon.value)"
        >
            <Icon :name="icon.value" class="h-5 w-5" aria-hidden="true" />
        </button>
    </div>
</template>
