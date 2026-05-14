<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type OrderItem = {
    product_line: string;
    product_name: string;
    set_name: string;
    condition: string;
    quantity: number;
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

    if (order.buyer_name) {
        lines.push(order.buyer_name);
    }

    if (order.address1) {
        lines.push(order.address1);
    }

    if (order.address2) {
        lines.push(order.address2);
    }

    const cityLine = [order.city, order.state].filter(Boolean).join(', ');
    const cityPostal = [cityLine, order.postal_code].filter(Boolean).join(' ');

    if (cityPostal) {
        lines.push(cityPostal);
    }

    if (order.country && order.country !== 'US') {
        lines.push(order.country);
    }

    return lines;
}

function sheetQty(sheet: Sheet): number {
    return sheet.items.reduce((sum, i) => sum + i.quantity, 0);
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
                    <div v-for="(line, i) in recipientLines(order)" :key="i">
                        {{ line }}
                    </div>
                </div>

                <!-- Brand logo — right side of middle panel -->
                <div class="brand-logo">
                    <img src="/logo.png" alt="Mythic Fox Games" />
                </div>
            </div>

            <!-- ══ SIDE B — Packing slip ══ -->
            <div
                class="slip-page side-b"
                :data-test="`side-b-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
            >
                <!-- Fold guides at 3.5" and 7.5" -->
                <div class="fold-guide fold-guide--top"></div>
                <div class="fold-guide fold-guide--bottom"></div>

                <!-- Content area (1" left, 0.5" top) -->
                <div class="slip-content">
                    <!-- Sheet X of N indicator (multi-sheet only) -->
                    <div
                        v-if="sheet.sheet_total > 1"
                        class="sheet-indicator"
                        :data-test="`sheet-indicator-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
                    >
                        Sheet {{ sheet.sheet_index }} of {{ sheet.sheet_total }}
                    </div>

                    <!-- Two-column order header -->
                    <div
                        class="order-header"
                        :data-test="`order-header-${order.tcgplayer_order_number}`"
                    >
                        <div class="order-header__col">
                            <div>
                                <span class="key">ORDER NUMBER</span>
                                <span class="value">{{
                                    order.tcgplayer_order_number
                                }}</span>
                            </div>
                            <div>
                                <span class="key">ORDER AMOUNT</span>
                                <span class="value">{{
                                    order.total_amount_formatted
                                }}</span>
                            </div>
                        </div>
                        <div class="order-header__col">
                            <div>
                                <span class="key">BUYER NAME</span>
                                <span class="value">{{
                                    order.buyer_name
                                }}</span>
                            </div>
                            <div>
                                <span class="key">ORDER DATE</span>
                                <span class="value">{{
                                    order.order_date
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card table -->
                    <table
                        class="card-table"
                        :data-test="`card-table-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
                    >
                        <thead>
                            <tr>
                                <th class="col-game">GAME</th>
                                <th class="col-name">CARD NAME</th>
                                <th class="col-set">SET</th>
                                <th class="col-cond">COND.</th>
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
                                    {{ item.product_line }}
                                </td>
                                <td class="col-name truncate">
                                    {{ item.product_name }}
                                </td>
                                <td class="col-set">{{ item.set_name }}</td>
                                <td class="col-cond">{{ item.condition }}</td>
                                <td class="col-qty">{{ item.quantity }}</td>
                            </tr>
                            <!-- Per-sheet total row -->
                            <tr
                                class="total-row"
                                :data-test="`total-row-${order.tcgplayer_order_number}-${sheet.sheet_index}`"
                            >
                                <td colspan="4" class="total-label">
                                    TOTAL NUMBER OF CARDS
                                </td>
                                <td class="col-qty">{{ sheetQty(sheet) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Footer contact block (on every sheet) -->
                    <div
                        class="slip-footer"
                        :data-test="`slip-footer-${order.tcgplayer_order_number}`"
                    >
                        <p>
                            If you have any questions or concerns about this
                            order:
                        </p>
                        <ol>
                            <li>
                                Email me directly at
                                <span class="contact-email"
                                    >josh@mythicfoxgames.com</span
                                >
                            </li>
                            <li>
                                Message me via your TCGplayer order history page
                            </li>
                            <li>
                                Leave feedback by clicking &ldquo;Rate
                                Transaction&rdquo; on your TCGplayer order
                                history page
                            </li>
                        </ol>
                    </div>
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

/* Return address: rows 4.125"–5.125", inset 0.875" from left. */
.return-address {
    position: absolute;
    top: 4.125in;
    left: 0.875in;
    width: 3.5in;
    height: 1in;
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.4;
}

/* Recipient address: rows 5.75"–7", inset 0.875" from left. */
.recipient-address {
    position: absolute;
    top: 5.75in;
    left: 0.875in;
    width: 3.5in;
    height: 1.25in;
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.4;
}

/* Brand logo: ~1.5" wide, right edge ~1" from right, centered in 4" band. */
.brand-logo {
    position: absolute;
    top: 3.5in;
    right: 1in;
    width: 1.5in;
    height: 4in;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.brand-logo img {
    width: 1.5in;
    height: auto;
    display: block;
}

/* ══ SIDE B ════════════════════════════════════════════════════ */

/* Fold guides: hairline segments at 3.5" and 7.5" from each edge. */
.fold-guide {
    position: absolute;
    left: 0;
    right: 0;
    height: 0;
}

.fold-guide--top {
    top: 3.5in;
}

.fold-guide--bottom {
    top: 7.5in;
}

.fold-guide::before,
.fold-guide::after {
    content: '';
    display: block;
    position: absolute;
    top: 0;
    width: 0.75in;
    border-top: 0.5pt solid rgba(0, 0, 0, 0.3);
}

.fold-guide::before {
    left: 0;
}

.fold-guide::after {
    right: 0;
}

/* Content area: 1" left, 0.5" top/bottom */
.slip-content {
    position: absolute;
    top: 0.5in;
    left: 1in;
    width: 6.5in;
    bottom: 0.5in;
    box-sizing: border-box;
}

/* ── Sheet X of N indicator ─────────────────────────────────── */
.sheet-indicator {
    font-size: 9pt;
    font-weight: bold;
    text-align: right;
    margin-bottom: 4pt;
}

/* ── Order header ───────────────────────────────────────────── */
.order-header {
    display: flex;
    gap: 0.5in;
    margin-bottom: 0.2in;
}

.order-header__col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2pt;
}

.order-header__col > div {
    display: flex;
    gap: 6pt;
}

.key {
    font-weight: bold;
}

/* ── Card table ─────────────────────────────────────────────── */
.card-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10pt;
    table-layout: fixed;
}

.card-table th,
.card-table td {
    padding: 2pt 3pt;
    text-align: left;
    vertical-align: top;
}

.card-table thead th {
    font-weight: bold;
    border-bottom: 0.5pt solid #000;
}

.card-table tbody tr + tr td {
    border-top: 0.25pt solid #ccc;
}

.col-game {
    width: 0.6in;
}

.col-name {
    width: 2.6in;
}

.col-set {
    width: 2in;
}

.col-cond {
    width: 0.7in;
}

.col-qty {
    width: 0.6in;
    text-align: right;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.total-row td {
    font-weight: bold;
    border-top: 0.5pt solid #000 !important;
}

.total-label {
    text-align: left;
}

/* ── Footer ─────────────────────────────────────────────────── */
.slip-footer {
    margin-top: 0.2in;
    font-size: 9pt;
    line-height: 1.4;
}

.slip-footer ol {
    margin: 4pt 0 0 1.2em;
    padding: 0;
}

.slip-footer li {
    margin-bottom: 2pt;
}
</style>
