<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { LayoutGrid, List } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

type AccountsViewMode = 'grid' | 'list';

defineProps<{
    modelValue: AccountsViewMode;
}>();

defineEmits<{
    'update:modelValue': [mode: AccountsViewMode];
}>();

const { t } = useI18n();
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <div
            role="group"
            class="inline-flex gap-0.5 rounded-xl border border-input bg-muted/30 p-0.5"
            :aria-label="t('accounts.index.view.toggleAria')"
        >
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        type="button"
                        :variant="modelValue === 'grid' ? 'secondary' : 'ghost'"
                        size="icon"
                        class="size-8 rounded-lg"
                        :aria-label="t('accounts.index.view.grid')"
                        :aria-pressed="modelValue === 'grid'"
                        @click="$emit('update:modelValue', 'grid')"
                    >
                        <LayoutGrid class="size-4" :stroke-width="1.7" aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ t('accounts.index.view.grid') }}</TooltipContent>
            </Tooltip>

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        type="button"
                        :variant="modelValue === 'list' ? 'secondary' : 'ghost'"
                        size="icon"
                        class="size-8 rounded-lg"
                        :aria-label="t('accounts.index.view.list')"
                        :aria-pressed="modelValue === 'list'"
                        @click="$emit('update:modelValue', 'list')"
                    >
                        <List class="size-4" :stroke-width="1.7" aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ t('accounts.index.view.list') }}</TooltipContent>
            </Tooltip>
        </div>
    </TooltipProvider>
</template>
