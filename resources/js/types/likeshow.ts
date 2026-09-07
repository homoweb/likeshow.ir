export type PlatformValue = string;

export type ProductTypeValue = string;

export type OrderStatusValue =
    | 'pending'
    | 'processing'
    | 'completed'
    | 'canceled'
    | 'failed';

export type PaymentStatusValue = 'unpaid' | 'paid' | 'failed' | 'refunded';

export type PaymentTxnStatusValue =
    | 'pending'
    | 'success'
    | 'failed'
    | 'canceled';

export type PriceTier = {
    min_quantity: number;
    max_quantity: number;
    price: number;
};

export type Product = {
    id: number;
    type: ProductTypeValue;
    type_name?: string | null;
    platform: PlatformValue;
    platform_name?: string | null;
    title: string;
    description: string | null;
    min_quantity: number;
    max_quantity: number;
    step_quantity: number;
    base_price: number;
    prices?: PriceTier[];
    is_active?: boolean;
};

/** A dynamic product taxonomy row (platform or service type). */
export type TaxonomyTerm = {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    sort_order: number;
    products_count: number;
};

export type OrderUser = {
    id: number;
    name: string;
    email: string;
};

export type OrderPayment = {
    id: number;
    uuid?: string | null;
    amount: number;
    gateway?: string | null;
    authority?: string | null;
    reference_id?: string | null;
    status?: PaymentTxnStatusValue;
    paid_at?: string | null;
    created_at?: string | null;
};

export type Order = {
    id: number;
    order_number: string;
    product_title: string;
    target_username: string;
    quantity: number;
    unit_price?: number;
    total_price: number;
    status: OrderStatusValue;
    payment_status: PaymentStatusValue;
    paid_at?: string | null;
    created_at?: string | null;
    user?: OrderUser | null;
    payments?: OrderPayment[];
};

export type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role?: string;
    roles: string[];
    created_at?: string | null;
};

export type Option = {
    value: string;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type SharedUrls = {
    main: { home: string };
    panel: {
        home: string;
        orders: string;
        login: string;
        register: string;
        logout: string;
    };
    admin: { home: string; login: string; logout: string };
};

export type SharedProps = {
    name: string;
    siteUrl: string;
    urls: SharedUrls;
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            is_admin: boolean;
        } | null;
    };
    flash: { success?: string; error?: string; info?: string };
};
