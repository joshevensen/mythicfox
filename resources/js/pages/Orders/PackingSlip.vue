<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { abbreviateGame } from '@/lib/gameAbbreviations';

type OrderItem = {
    product_line: string;
    product_name: string;
    set_name: string;
    condition: string;
    quantity: number;
    number: string;
    unit_price: number | null;
    total_price: number | null;
};

type Sheet = {
    sheet_index: number;
    sheet_total: number;
    items: OrderItem[];
};

type OrderData = {
    id: number;
    tcgplayer_order_number: string;
    buyer_name: string;
    address1: string | null;
    address2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    order_date: string | null;
    total_amount_formatted: string;
    shipping_method: string | null;
    sheets: Sheet[];
};

type ReturnAddress = {
    name: string;
    line1: string;
    line2: string;
};

const props = defineProps<{
    orders: OrderData[];
    returnAddress: ReturnAddress;
}>();

function recipientLines(order: OrderData): string[] {
    const lines: string[] = [];

    if (order.buyer_name) lines.push(order.buyer_name);
    if (order.address1) lines.push(order.address1);
    if (order.address2) lines.push(order.address2);

    const cityLine = [order.city, order.state].filter(Boolean).join(', ');
    const cityPostal = [cityLine, order.postal_code].filter(Boolean).join(' ');
    if (cityPostal) lines.push(cityPostal);

    if (order.country && order.country !== 'US') lines.push(order.country);

    return lines;
}

function abbreviateCondition(cond: string): string {
    const c = cond.toLowerCase();
    if (c.startsWith('near mint')) return 'NM';
    if (c.startsWith('lightly played')) return 'LP';
    if (c.startsWith('moderately played')) return 'MP';
    if (c.startsWith('heavily played')) return 'HP';
    if (c.startsWith('damaged')) return 'D';
    return cond.length <= 3 ? cond : cond.slice(0, 3);
}

function formatCents(cents: number | null): string {
    if (cents == null) return '—';
    return '$' + (cents / 100).toFixed(2);
}

function sheetQty(sheet: Sheet): number {
    return sheet.items.reduce((sum, i) => sum + i.quantity, 0);
}

function sheetTotalPrice(sheet: Sheet): string {
    const total = sheet.items.reduce((sum, i) => sum + (i.total_price ?? 0), 0);
    return formatCents(total);
}
</script>

<template>
    <Head title="Packing slip" />

    <!-- Screen-only print prompt (hidden in @media print via CSS) -->
    <div class="print-prompt">
        <p>
            Press <kbd>Cmd/Ctrl+P</kbd> to print. Set duplex to
            <strong>Long-edge flip</strong>.
        </p>
    </div>

    <!-- One set of page-pairs per order; one pair per sheet -->
    <template v-for="order in props.orders" :key="order.id">
        <template
            v-for="sheet in order.sheets"
            :key="`${order.id}-${sheet.sheet_index}`"
        >
            <!-- ══ SIDE A — Address panel ══ -->
            <div
                class="slip-page side-a"
                :data-test="`side-a-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
            >
                <!-- Order info box — top section (above address windows) -->
                <div
                    class="order-info-box"
                    :data-test="`order-header-${order.tcgplayer_order_number}`"
                >
                    <div class="order-info-col">
                        <div class="info-row">
                            <span class="info-label">ORDER #</span>
                            <span>{{ order.tcgplayer_order_number }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">BUYER</span>
                            <span>{{ order.buyer_name }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">SELLER</span>
                            <span>{{ returnAddress.name }}</span>
                        </div>
                    </div>
                    <div class="order-info-col">
                        <div class="info-row">
                            <span class="info-label">DATE</span>
                            <span>{{ order.order_date }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">AMOUNT</span>
                            <span>{{ order.total_amount_formatted }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">SHIPPING</span>
                            <span>{{ order.shipping_method ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Return address — upper window (4.125"–5.125") -->
                <div class="return-address" data-test="return-address">
                    <div>{{ returnAddress.name }}</div>
                    <div>{{ returnAddress.line1 }}</div>
                    <div>{{ returnAddress.line2 }}</div>
                </div>

                <!-- Recipient address — lower window (5.75"–7") -->
                <div
                    class="recipient-address"
                    :data-test="`recipient-address-${order.tcgplayer_order_number}`"
                >
                    <div
                        v-for="(line, i) in recipientLines(order)"
                        :key="i"
                        :class="{ 'buyer-name': i === 0 && !!order.buyer_name }"
                    >
                        {{ line }}
                    </div>
                </div>

                <!-- Brand logo — right side of middle panel -->
                <div class="brand-logo">
                    <img src="/logo.png" alt="Mythic Fox Games" />
                </div>

                <!-- Thank you + contact — bottom section (below address windows) -->
                <div
                    class="side-a-footer"
                    :data-test="`slip-footer-${order.tcgplayer_order_number}`"
                >
                    <p class="thank-you-heading">Thank you for your order</p>
                    <div class="footer-columns">
                        <div class="footer-col">
                            <p class="footer-col-heading">For Any Questions About Your Order:</p>
                            <ol>
                                <li>Please contact the seller directly by logging into your account and navigating to the Order History page.</li>
                                <li>Click the &ldquo;Contact Seller&rdquo; link to compose a message to the seller and let them know of the issue.</li>
                                <li>If the seller does not respond to your message within 2 business days, or if they aren&rsquo;t able to assist you please contact TCGplayer customer service via <strong>help.tcgplayer.com</strong>.</li>
                            </ol>
                        </div>
                        <div class="footer-col">
                            <p class="footer-col-heading">To Provide Feedback for This Order:</p>
                            <p>If you have an issue with the order, it&rsquo;s best to contact the seller first using the steps on the left in order to give them an opportunity to correct the issue for you.</p>
                            <ol>
                                <li>Log into your account to the Order History page.</li>
                                <li>Click on the &ldquo;Rate Transaction&rdquo; button to leave feedback for your order.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ SIDE B — Card table ══ -->
            <div
                class="slip-page side-b"
                :data-test="`side-b-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
            >
                <!-- Fold guide: fold top section down until its edge meets this line -->
                <div class="fold-guide fold-guide--bottom"></div>

                <!-- Content area -->
                <div class="slip-content">
                    <!-- Order number left + sheet indicator right -->
                    <div
                        class="table-subheader"
                        :data-test="`sheet-indicator-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
                    >
                        <span>{{ order.tcgplayer_order_number }}</span>
                        <span>Sheet {{ sheet.sheet_index }} of {{ sheet.sheet_total }}</span>
                    </div>

                    <table
                        class="card-table"
                        :data-test="`card-table-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
                    >
                        <thead>
                            <tr>
                                <th class="col-game">GAME</th>
                                <th class="col-set">SET</th>
                                <th class="col-name">CARD NAME</th>
                                <th class="col-num">#</th>
                                <th class="col-price">PRICE</th>
                                <th class="col-qty">QTY</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in sheet.items"
                                :key="idx"
                                :data-test="`card-row-${order.tcgplayer_order_number}-${sheet.sheet_index}-${idx}`"
                            >
                                <td class="col-game">
                                    {{ abbreviateGame(item.product_line) }}
                                </td>
                                <td class="col-set">
                                    {{ item.set_name }}
                                </td>
                                <td class="col-name">
                                    {{ item.product_name }}
                                    <span class="cond-tag">[{{ abbreviateCondition(item.condition) }}]</span>
                                </td>
                                <td class="col-num">{{ item.number }}</td>
                                <td class="col-price">{{ formatCents(item.unit_price) }}</td>
                                <td class="col-qty">{{ item.quantity }}</td>
                            </tr>
                            <!-- Per-sheet total row -->
                            <tr
                                class="total-row"
                                :data-test="`total-row-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
                            >
                                <td colspan="4" class="total-label">TOTAL</td>
                                <td class="col-price">{{ sheetTotalPrice(sheet) }}</td>
                                <td class="col-qty">{{ sheetQty(sheet) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </template>
</template>

<style>
/* ── Print page setup ───────────────────────────────────────── */
@page {
    size: letter;
    margin: 0;
}

/* ── Screen prompt ──────────────────────────────────────────── */
.print-prompt {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f9f9;
    color: #333;
    font-family: Helvetica, Arial, sans-serif;
    font-size: 14px;
    z-index: 9999;
}

@media print {
    .print-prompt {
        display: none;
    }
}

/* ── Page container ─────────────────────────────────────────── */
.slip-page {
    position: relative;
    width: 8.5in;
    height: 11in;
    overflow: hidden;
    page-break-after: always;
    box-sizing: border-box;
    background: #fff;
    color: #000;
    font-family: Helvetica, Arial, sans-serif;
    font-size: 10pt;
}

/* ══ SIDE A ════════════════════════════════════════════════════ */

/* Order info box — sits in the top third, above the address windows. */
.order-info-box {
    position: absolute;
    top: 1.5in;
    left: 0.875in;
    right: 0.875in;
    display: flex;
    gap: 0.5in;
    border: 1pt solid #ccc;
    padding: 16px;
}

.order-info-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5pt;
}

.info-row {
    display: flex;
    gap: 6pt;
}

.info-label {
    font-weight: bold;
    min-width: 0.75in;
}

/* Return address: rows 3.625"–4.625", inset 0.875" from left. */
.return-address {
    position: absolute;
    top: 3.625in;
    left: 0.875in;
    width: 3.5in;
    height: 1in;
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.4;
}

/* Recipient address: rows 5.25"–6.5", inset 0.875" from left. */
.recipient-address {
    position: absolute;
    top: 5.25in;
    left: 0.875in;
    width: 3.5in;
    height: 1.25in;
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.4;
}

.buyer-name {
    font-weight: bold;
}

/* Brand logo: ~3" wide, right edge ~1" from right, centered in 4" band. */
.brand-logo {
    position: absolute;
    top: 3in;
    right: 1in;
    width: 3in;
    height: 4in;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.brand-logo img {
    width: 3in;
    height: auto;
    display: block;
}

/* Thank you + contact — bottom third of Side A, below address windows. */
.side-a-footer {
    position: absolute;
    bottom: 0.75in;
    left: 0.875in;
    right: 0.875in;
    font-size: 9pt;
    line-height: 1.4;
}

.thank-you-heading {
    font-weight: bold;
    font-size: 14pt;
    text-align: center;
    margin: 0 0 10pt 0;
}

.footer-columns {
    display: flex;
    gap: 0.4in;
}

.footer-col {
    flex: 1;
}

.footer-col-heading {
    font-size: 11pt;
    font-weight: bold;
    margin: 0 0 3pt 0;
}

.footer-col p:not(.footer-col-heading) {
    margin: 0 0 4pt 0;
}

.footer-col ol {
    margin: 0 0 0 1.2em;
    padding: 0;
    list-style-type: decimal;
}

.footer-col li {
    margin-bottom: 2pt;
}

/* ══ SIDE B ════════════════════════════════════════════════════ */

/* Fold guide: marks where the top flap's edge lands (6" from top) so you know
   where to crease. The second fold uses that crease edge as its own reference. */
.fold-guide {
    position: absolute;
    left: 0;
    right: 0;
    height: 0;
}

.fold-guide--bottom {
    top: 6in;
}

.fold-guide::before,
.fold-guide::after {
    content: '';
    display: block;
    position: absolute;
    top: 0;
    width: 0.5in;
    border-top: 0.5pt solid rgba(0, 0, 0, 0.3);
}

.fold-guide::before {
    left: 0;
}

.fold-guide::after {
    right: 0;
}

/* Content area */
.slip-content {
    position: absolute;
    top: 0.5in;
    left: 0.75in;
    width: 7in;
    bottom: 0.5in;
    box-sizing: border-box;
}

/* Table subheader: order number left, sheet indicator right */
.table-subheader {
    display: flex;
    justify-content: space-between;
    font-size: 9pt;
    font-weight: bold;
    margin-bottom: 3pt;
}

/* ── Card table ─────────────────────────────────────────────── */
.card-table {
    width: 100%;
    margin-top: 0.5in;
    border-collapse: collapse;
    font-size: 11px;
    table-layout: fixed;
}

.card-table tbody tr {
    height: 40px; /* 2 lines × 13px + 7px top + 5px bottom */
}

.card-table th,
.card-table td {
    padding: 7px 3pt;
    line-height: 13px;
    text-align: left;
    vertical-align: middle;
    overflow: hidden;
    white-space: normal;
    word-break: break-word;
}

.card-table thead th {
    font-size: 9px !important;
    font-weight: bold;
    border-bottom: 0.5pt solid #000;
    padding-top: 3pt;
    padding-bottom: 3pt;
}

.card-table tbody tr + tr td {
    border-top: 0.25pt solid #ccc;
}

.col-game {
    width: 0.5in;
}

.col-set {
    width: 1.5in;
}

.col-name {
    width: 3.7in;
}

.col-num {
    width: 0.5in;
    font-size: 10px !important;
}

.col-price {
    width: 0.4in;
    text-align: right !important;
}

.col-qty {
    width: 0.4in;
    text-align: right !important;
}

.cond-tag {
    color: #555;
    font-size: 0.9em;
}

.total-row td {
    font-weight: bold;
    border-top: 0.5pt solid #000 !important;
    padding-top: 4pt;
    padding-bottom: 4pt;
}

.total-label {
    text-align: left;
}
</style>
