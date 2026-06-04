export type CategoryOption = {
    id: number;
    name: string;
    type: string;
    sort_order: number;
    icon: string;
    color: string;
};

export function categoriesByIdMap(categories: CategoryOption[]): Map<number, CategoryOption> {
    return new Map(categories.map((c) => [c.id, c]));
}

export function filterCategoriesByType(categories: CategoryOption[], type: 'income' | 'expense'): CategoryOption[] {
    return [...categories].filter((c) => c.type === type).sort((a, b) => a.sort_order - b.sort_order);
}

export function firstCategoryId(categories: CategoryOption[]): number | null {
    return categories[0]?.id ?? null;
}
