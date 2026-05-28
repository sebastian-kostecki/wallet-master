<script setup lang="ts">
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type ResourceLinksObject = {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
};

type SimplePaginator = {
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    per_page: number;
};

type ResourcePaginator = {
    links?: ResourceLinksObject;
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        links: PaginatorLink[];
    };
};

type Paginator = SimplePaginator | ResourcePaginator;

const props = withDefaults(
    defineProps<{
        paginator: Paginator;
        query: Record<string, unknown>;
        perPageOptions?: number[];
        preserveScroll?: boolean;
    }>(),
    {
        perPageOptions: () => [10, 25, 50, 100],
        preserveScroll: true,
    },
);

const { t } = useI18n();

const perPageDropdownOptions = computed<DropdownOption<number>[]>(() => {
    return props.perPageOptions.map((value) => ({ value, label: String(value) }));
});

function normalizeLabel(label: string): string {
    return label
        .replace(/<[^>]+>/g, '')
        .replace(/&laquo;|&raquo;|&lsaquo;|&rsaquo;/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

const paginatorLinks = computed<PaginatorLink[]>(() => {
    const paginator = props.paginator as any;

    if (Array.isArray(paginator?.links)) {
        return paginator.links;
    }

    if (Array.isArray(paginator?.meta?.links)) {
        return paginator.meta.links;
    }

    return [];
});

const currentPage = computed(() => {
    const paginator = props.paginator as any;

    if (typeof paginator?.current_page === 'number') {
        return paginator.current_page;
    }

    if (typeof paginator?.meta?.current_page === 'number') {
        return paginator.meta.current_page;
    }

    return 1;
});

const lastPage = computed(() => {
    const paginator = props.paginator as any;

    if (typeof paginator?.last_page === 'number') {
        return paginator.last_page;
    }

    if (typeof paginator?.meta?.last_page === 'number') {
        return paginator.meta.last_page;
    }

    return 1;
});

const perPage = computed(() => {
    const paginator = props.paginator as any;

    if (typeof paginator?.per_page === 'number') {
        return paginator.per_page;
    }

    if (typeof paginator?.meta?.per_page === 'number') {
        return paginator.meta.per_page;
    }

    return props.perPageOptions[0] ?? 10;
});

const prevUrl = computed(() => {
    const paginator = props.paginator as any;
    const resourcePrev = paginator?.links?.prev ?? null;
    if (typeof resourcePrev === 'string' || resourcePrev === null) {
        return resourcePrev;
    }

    return paginatorLinks.value[0]?.url ?? null;
});

const nextUrl = computed(() => {
    const paginator = props.paginator as any;
    const resourceNext = paginator?.links?.next ?? null;
    if (typeof resourceNext === 'string' || resourceNext === null) {
        return resourceNext;
    }

    return paginatorLinks.value[paginatorLinks.value.length - 1]?.url ?? null;
});

const middleLinks = computed(() => {
    if (paginatorLinks.value.length <= 2) {
        return [];
    }

    return paginatorLinks.value.slice(1, -1).map((l) => ({
        ...l,
        label: normalizeLabel(l.label),
    }));
});

function changePerPage(next: number) {
    router.get(
        route('transactions.index'),
        {
            ...props.query,
            per_page: next,
            page: undefined,
        },
        {
            preserveScroll: props.preserveScroll,
            replace: true,
            preserveState: 'errors',
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <span class="text-xs text-muted-foreground">{{ t('transactions.index.pagination.perPageLabel') }}</span>
            <div class="w-24">
                <DropdownSelect
                    id="per_page"
                    :model-value="perPage"
                    :options="perPageDropdownOptions"
                    :placeholder="String(perPage)"
                    size="sm"
                    @update:model-value="(value: any) => changePerPage(Number(value))"
                />
            </div>
        </div>

        <div class="text-xs text-muted-foreground">
            {{ t('transactions.index.pagination.pageOf', { page: currentPage, pages: lastPage }) }}
        </div>

        <nav class="flex items-center gap-1" :aria-label="t('transactions.index.pagination.aria')">
            <Button variant="outline" size="sm" as-child :disabled="!prevUrl">
                <Link :href="prevUrl ?? ''" :preserve-scroll="preserveScroll">
                    {{ t('transactions.index.pagination.prev') }}
                </Link>
            </Button>

            <div class="hidden items-center gap-1 sm:flex">
                <Link
                    v-for="link in middleLinks"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :preserve-scroll="preserveScroll"
                    :class="
                        cn(
                            'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm font-medium transition-colors',
                            link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        )
                    "
                >
                    {{ link.label }}
                </Link>
            </div>

            <Button variant="outline" size="sm" as-child :disabled="!nextUrl">
                <Link :href="nextUrl ?? ''" :preserve-scroll="preserveScroll">
                    {{ t('transactions.index.pagination.next') }}
                </Link>
            </Button>
        </nav>
    </div>
</template>
