import type {
    OrderStatusValue,
    PaymentStatusValue,
    PaymentTxnStatusValue,
    Product,
} from '@/types/likeshow';

const faNumber = new Intl.NumberFormat('fa-IR');

export const toFa = (value: number | string): string =>
    faNumber.format(Number(value));

export const formatPrice = (price: number): string => `${toFa(price)} تومان`;

export const formatDate = (iso: string | null | undefined): string => {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('fa-IR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
};

export const ORDER_STATUS_LABELS: Record<OrderStatusValue, string> = {
    pending: 'در انتظار پرداخت',
    processing: 'در حال انجام',
    completed: 'تکمیل شده',
    canceled: 'لغو شده',
    failed: 'ناموفق',
};

export const ORDER_STATUS_BADGES: Record<OrderStatusValue, string> = {
    pending: 'bg-amber-500/10 text-amber-400 ring-amber-500/30',
    processing: 'bg-sky-500/10 text-sky-400 ring-sky-500/30',
    completed: 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/30',
    canceled: 'bg-zinc-500/10 text-zinc-400 ring-zinc-500/30',
    failed: 'bg-rose-500/10 text-rose-400 ring-rose-500/30',
};

export const PAYMENT_STATUS_LABELS: Record<PaymentStatusValue, string> = {
    unpaid: 'پرداخت نشده',
    paid: 'پرداخت شده',
    failed: 'پرداخت ناموفق',
    refunded: 'بازگشت وجه',
};

export const PAYMENT_STATUS_BADGES: Record<PaymentStatusValue, string> = {
    unpaid: 'bg-amber-500/10 text-amber-400 ring-amber-500/30',
    paid: 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/30',
    failed: 'bg-rose-500/10 text-rose-400 ring-rose-500/30',
    refunded: 'bg-violet-500/10 text-violet-400 ring-violet-500/30',
};

/** Status of an individual gateway transaction (payments table). */
export const TXN_STATUS_LABELS: Record<PaymentTxnStatusValue, string> = {
    pending: 'در انتظار نتیجه',
    success: 'موفق',
    failed: 'ناموفق',
    canceled: 'لغو شده',
};

export const TXN_STATUS_BADGES: Record<PaymentTxnStatusValue, string> = {
    pending: 'bg-amber-500/10 text-amber-400 ring-amber-500/30',
    success: 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/30',
    failed: 'bg-rose-500/10 text-rose-400 ring-rose-500/30',
    canceled: 'bg-zinc-500/10 text-zinc-400 ring-zinc-500/30',
};

export const badgeClass = (base: string): string =>
    `inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${base}`;

export const statusBadge = (status: OrderStatusValue): string =>
    ORDER_STATUS_BADGES[status] ?? ORDER_STATUS_BADGES.pending;

export const paymentBadge = (status: PaymentStatusValue): string =>
    PAYMENT_STATUS_BADGES[status] ?? PAYMENT_STATUS_BADGES.unpaid;

/**
 * Client-side mirror of the server pricing (per 1000 units, IRT).
 * The backend remains the single source of truth.
 */
export const unitPriceFor = (product: Product, quantity: number): number => {
    const tier = (product.prices ?? []).find(
        (t) => quantity >= t.min_quantity && quantity <= t.max_quantity,
    );

    return tier ? tier.price : product.base_price;
};

export const totalPriceFor = (product: Product, quantity: number): number =>
    Math.floor((unitPriceFor(product, quantity) * quantity) / 1000);
