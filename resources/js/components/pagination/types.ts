export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type ResourceLinksObject = {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
};

export type SimplePaginator = {
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    per_page: number;
};

export type ResourcePaginator = {
    links?: ResourceLinksObject;
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        links: PaginatorLink[];
    };
};

export type Paginator = SimplePaginator | ResourcePaginator;
