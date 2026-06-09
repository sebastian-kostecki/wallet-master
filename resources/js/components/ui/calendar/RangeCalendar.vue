<script setup lang="ts">
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    start: string; // YYYY-MM-DD or ''
    end: string; // YYYY-MM-DD or ''
    class?: string;
}>();

const emit = defineEmits<{
    (e: 'select', isoDate: string): void;
}>();

const { locale } = useI18n();

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

function isIsoDate(value: string): boolean {
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function isoToParts(value: string): { y: number; m: number; d: number } | null {
    if (!isIsoDate(value)) {
        return null;
    }

    const [y, m, d] = value.split('-').map((p) => Number(p));
    if (!y || !m || !d) {
        return null;
    }

    const dt = new Date(Date.UTC(y, m - 1, d));
    if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== m - 1 || dt.getUTCDate() !== d) {
        return null;
    }

    return { y, m, d };
}

function toIsoDate(y: number, m: number, d: number): string {
    return `${y}-${pad2(m)}-${pad2(d)}`;
}

function daysInMonth(y: number, m: number): number {
    return new Date(Date.UTC(y, m, 0)).getUTCDate();
}

function addMonths(y: number, m: number, delta: number): { y: number; m: number } {
    const idx = (y * 12 + (m - 1)) + delta;
    const nextY = Math.floor(idx / 12);
    const nextM = (idx % 12) + 1;
    return { y: nextY, m: nextM };
}

function dayOfWeekMondayStart(y: number, m: number, d: number): number {
    // 0..6, where 0 is Monday
    const js = new Date(Date.UTC(y, m - 1, d)).getUTCDay(); // 0=Sun..6=Sat
    return (js + 6) % 7;
}

function compareIso(a: string, b: string): number {
    return a.localeCompare(b);
}

const startParts = computed(() => isoToParts(props.start));

const viewYear = ref<number>(startParts.value?.y ?? new Date().getUTCFullYear());
const viewMonth = ref<number>(startParts.value?.m ?? new Date().getUTCMonth() + 1);

watch(
    () => props.start,
    (next) => {
        const p = isoToParts(next);
        if (!p) {
            return;
        }
        viewYear.value = p.y;
        viewMonth.value = p.m;
    },
);

const monthLabel = computed(() => {
    const resolvedLocale = locale.value === 'pl' ? 'pl-PL' : 'en-US';
    const dt = new Date(Date.UTC(viewYear.value, viewMonth.value - 1, 1));
    return new Intl.DateTimeFormat(resolvedLocale, { month: 'long', year: 'numeric' }).format(dt);
});

const weekdayLabels = computed(() => {
    const resolvedLocale = locale.value === 'pl' ? 'pl-PL' : 'en-US';
    const base = new Date(Date.UTC(2024, 0, 1)); // Monday
    return Array.from({ length: 7 }, (_, i) => {
        const dt = new Date(base.getTime() + i * 24 * 60 * 60 * 1000);
        return new Intl.DateTimeFormat(resolvedLocale, { weekday: 'short' }).format(dt);
    });
});

const grid = computed(() => {
    const y = viewYear.value;
    const m = viewMonth.value;

    const leading = dayOfWeekMondayStart(y, m, 1);
    const dim = daysInMonth(y, m);

    const cells: Array<{ iso: string; day: number; isCurrentMonth: boolean }> = [];

    const prev = addMonths(y, m, -1);
    const dimPrev = daysInMonth(prev.y, prev.m);
    for (let i = leading - 1; i >= 0; i--) {
        const day = dimPrev - i;
        cells.push({ iso: toIsoDate(prev.y, prev.m, day), day, isCurrentMonth: false });
    }

    for (let day = 1; day <= dim; day++) {
        cells.push({ iso: toIsoDate(y, m, day), day, isCurrentMonth: true });
    }

    const target = 42;
    const next = addMonths(y, m, 1);
    let day = 1;
    while (cells.length < target) {
        cells.push({ iso: toIsoDate(next.y, next.m, day), day, isCurrentMonth: false });
        day++;
    }

    return cells;
});

const rangeStart = computed(() => (props.start && isIsoDate(props.start) ? props.start : ''));
const rangeEnd = computed(() => (props.end && isIsoDate(props.end) ? props.end : ''));

function inRange(iso: string): boolean {
    if (!rangeStart.value || !rangeEnd.value) {
        return false;
    }
    const a = rangeStart.value;
    const b = rangeEnd.value;
    const min = compareIso(a, b) <= 0 ? a : b;
    const max = compareIso(a, b) <= 0 ? b : a;
    return compareIso(iso, min) >= 0 && compareIso(iso, max) <= 0;
}

function isEdge(iso: string): 'start' | 'end' | null {
    if (!rangeStart.value) {
        return null;
    }
    if (iso === rangeStart.value) {
        return 'start';
    }
    if (rangeEnd.value && iso === rangeEnd.value) {
        return 'end';
    }
    return null;
}

function prevMonth() {
    const next = addMonths(viewYear.value, viewMonth.value, -1);
    viewYear.value = next.y;
    viewMonth.value = next.m;
}

function nextMonth() {
    const next = addMonths(viewYear.value, viewMonth.value, 1);
    viewYear.value = next.y;
    viewMonth.value = next.m;
}

function select(iso: string) {
    emit('select', iso);
}
</script>

<template>
    <div :class="cn('w-72', props.class)">
        <div class="mb-2 flex items-center justify-between gap-2">
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :aria-label="'Previous month'"
                @click="prevMonth"
            >
                <ChevronLeft class="h-4 w-4" aria-hidden="true" />
            </button>

            <div class="min-w-0 flex-1 text-center text-sm font-medium capitalize">
                {{ monthLabel }}
            </div>

            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :aria-label="'Next month'"
                @click="nextMonth"
            >
                <ChevronRight class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
            <div v-for="w in weekdayLabels" :key="w" class="py-1">
                {{ w }}
            </div>
        </div>

        <div class="mt-1 grid grid-cols-7 gap-1">
            <button
                v-for="cell in grid"
                :key="cell.iso"
                type="button"
                :class="
                    cn(
                        'relative inline-flex h-9 w-9 items-center justify-center rounded-md text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        cell.isCurrentMonth ? 'text-foreground hover:bg-muted' : 'text-muted-foreground hover:bg-muted/70',
                        inRange(cell.iso) ? 'bg-primary/10' : '',
                        isEdge(cell.iso) ? 'bg-primary text-primary-foreground hover:bg-primary' : '',
                    )
                "
                @click="select(cell.iso)"
            >
                {{ cell.day }}
            </button>
        </div>
    </div>
</template>
