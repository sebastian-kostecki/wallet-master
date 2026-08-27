<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    year: number;
    month?: number;
    variant: 'monthly' | 'yearly';
}>();

const { t } = useI18n();

function changePeriod(delta: number): void {
    if (props.variant === 'yearly') {
        router.get(route('budget.yearly', { year: props.year + delta }));
        return;
    }

    let m = (props.month ?? 1) + delta;
    let y = props.year;
    if (m < 1) {
        m = 12;
        y -= 1;
    }
    if (m > 12) {
        m = 1;
        y += 1;
    }
    router.get(route('budget.monthly', { year: y, month: m }));
}
</script>

<template>
    <div class="flex items-center gap-1">
        <Button type="button" variant="ghost" size="icon" class="rounded-xl" :aria-label="t('budget.period.previous')" @click="changePeriod(-1)">
            <ChevronLeft class="size-4" :stroke-width="1.7" aria-hidden="true" />
        </Button>
        <span class="min-w-[5.5rem] text-center text-sm font-medium tabular-nums text-foreground">
            {{ variant === 'monthly' ? `${month}/${year}` : year }}
        </span>
        <Button type="button" variant="ghost" size="icon" class="rounded-xl" :aria-label="t('budget.period.next')" @click="changePeriod(1)">
            <ChevronRight class="size-4" :stroke-width="1.7" aria-hidden="true" />
        </Button>
    </div>
</template>
