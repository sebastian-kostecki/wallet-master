<script setup lang="ts">
import RangeCalendar from '@/components/ui/calendar/RangeCalendar.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Calendar as CalendarIcon, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    id?: string;
    from: string; // DD-MM-YYYY or ''
    to: string; // DD-MM-YYYY or ''
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:from', value: string): void;
    (e: 'update:to', value: string): void;
    (e: 'change'): void;
    (e: 'blur'): void;
}>();

const { t } = useI18n();

function ddMmYyyyToIso(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(trimmed);
    if (!m) {
        return '';
    }

    const [, dd, mm, yyyy] = m;
    return `${yyyy}-${mm}-${dd}`;
}

function isoToDdMmYyyy(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
    if (!m) {
        return '';
    }

    const [, yyyy, mm, dd] = m;
    return `${dd}-${mm}-${yyyy}`;
}

function compareIso(a: string, b: string): number {
    return a.localeCompare(b);
}

const open = ref(false);

const fromIso = computed(() => ddMmYyyyToIso(props.from));
const toIso = computed(() => ddMmYyyyToIso(props.to));

const displayValue = computed(() => {
    const f = props.from.trim();
    const t = props.to.trim();
    if (!f && !t) {
        return '';
    }
    if (f && !t) {
        return `${f} —`;
    }
    if (!f && t) {
        return `— ${t}`;
    }
    return `${f} — ${t}`;
});

function clear() {
    emit('update:from', '');
    emit('update:to', '');
    emit('change');
}

function setFromToIso(start: string, end: string) {
    emit('update:from', start ? isoToDdMmYyyy(start) : '');
    emit('update:to', end ? isoToDdMmYyyy(end) : '');
}

function onSelect(iso: string) {
    const start = fromIso.value;
    const end = toIso.value;

    if (!start || (start && end)) {
        setFromToIso(iso, '');
        return;
    }

    // start set, end not set
    if (compareIso(iso, start) < 0) {
        setFromToIso(iso, '');
        return;
    }

    setFromToIso(start, iso);
    emit('change');
    open.value = false;
}

function onBlur() {
    emit('blur');
}

function todayUtc(): Date {
    const now = new Date();
    return new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
}

function isoFromUtcDate(d: Date): string {
    const y = d.getUTCFullYear();
    const m = String(d.getUTCMonth() + 1).padStart(2, '0');
    const day = String(d.getUTCDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function startOfThisMonthIso(): string {
    const t0 = todayUtc();
    return isoFromUtcDate(new Date(Date.UTC(t0.getUTCFullYear(), t0.getUTCMonth(), 1)));
}

function endOfThisMonthIso(): string {
    const t0 = todayUtc();
    return isoFromUtcDate(new Date(Date.UTC(t0.getUTCFullYear(), t0.getUTCMonth() + 1, 0)));
}

function lastNDaysIso(n: number): { from: string; to: string } {
    const t0 = todayUtc();
    const to = isoFromUtcDate(t0);
    const from = isoFromUtcDate(new Date(t0.getTime() - (n - 1) * 24 * 60 * 60 * 1000));
    return { from, to };
}

function applyPresetThisMonth() {
    setFromToIso(startOfThisMonthIso(), endOfThisMonthIso());
    emit('change');
    open.value = false;
}

function applyPresetLast7Days() {
    const range = lastNDaysIso(7);
    setFromToIso(range.from, range.to);
    emit('change');
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :disabled="disabled"
                :class="
                    cn(
                        'h-10 w-full justify-between px-3 text-left font-normal',
                        !displayValue ? 'text-muted-foreground' : '',
                    )
                "
                @blur="onBlur"
            >
                <span class="truncate">
                    {{ displayValue || '—' }}
                </span>
                <span class="ml-2 inline-flex items-center gap-1">
                    <button
                        v-if="displayValue && !disabled"
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
                        :aria-label="'Clear date range'"
                        @click.stop="clear"
                    >
                        <X class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <CalendarIcon class="h-4 w-4 opacity-70" aria-hidden="true" />
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent class="p-3" align="start">
            <div class="flex w-full flex-col gap-3 sm:flex-row">
                <div class="flex flex-wrap gap-2 sm:w-40 sm:flex-col">
                    <button
                        type="button"
                        class="w-full rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
                        @click="applyPresetThisMonth"
                    >
                        {{ t('transactions.index.filters.presets.thisMonth') }}
                    </button>
                    <button
                        type="button"
                        class="w-full rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
                        @click="applyPresetLast7Days"
                    >
                        {{ t('transactions.index.filters.presets.last7Days') }}
                    </button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <RangeCalendar :start="fromIso" :end="toIso" @select="onSelect" />
                    <RangeCalendar :start="fromIso" :end="toIso" @select="onSelect" class="hidden sm:block" />
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
