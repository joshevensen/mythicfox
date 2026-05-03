# USPS Intelligent Mail Barcode (IMb) — Implementation Spec
> Laravel/Vue · HTML/CSS Print · Basic Service

---

## 1. IMb Data Structure

The barcode encodes a **20–31 digit string** from 5 concatenated fields:

| Field | Length | Value |
|---|---|---|
| Barcode Identifier | 2 digits | `00` |
| Service Type ID (STID) | 3 digits | `300` (Basic, First Class, no ACS) |
| Mailer ID (MID) | 9 digits | Assigned by USPS BCG |
| Serial Number | 6 digits | Your unique per-piece number |
| Routing Code | 0, 5, 9, or 11 digits | ZIP, ZIP+4, or ZIP+4+delivery point |

**Example string:** `00` + `300` + `123456789` + `000001` + `902101234` = `003001234567890000019021012340`

---

## 2. Encoding

The digit string must be encoded into a **65-character string** using the USPS IMb algorithm, then rendered via the USPS IMb font.

### Recommended approach (Laravel/Vue + HTML print)

1. **Encode server-side in PHP** — generate the 65-char string
2. **Pass to Vue** — render using the USPS IMb font via CSS `@font-face`
3. The font automatically renders the 65-char string as the correct barcode bars

### Font
- **File:** `USPSIMBStandard.ttf`
- **Download:** [PostalPro Fonts](https://postalpro.usps.com/mailing/intelligent-mail-barcode) (free, under "Resources")
- Embed via `@font-face` in your print stylesheet

### PHP Encoding Libraries
- [`gregs1104/imb`](https://github.com/gregs1104/imb) — PHP IMb encoder
- Or implement the USPS spec yourself (USPS-B-3200)

### Testing
- USPS online encoder/decoder: https://postalpro.usps.com/tools/encoder

---

## 3. Barcode Physical Specs

| Property | Requirement |
|---|---|
| Width | 2.667" – 3.225" |
| Height | 0.125" – 0.165" |
| Bar density | 22–24 bars per inch |
| Color | Black ink only |
| Font size | **12pt** (do not scale — breaks bar density) |

---

## 4. Placement on Packing Slip (Inside Address Block)

Place the IMb **below the city/state/zip line**:

| Requirement | Value |
|---|---|
| Gap above/below nearest text | ≥ 0.028" |
| Max distance from city/state/zip | ≤ 5/8" |
| Clearance left/right from other printing | ≥ 1/8" |
| From left/right envelope edges | ≥ 1/2" |
| From bottom of envelope | ≥ 5/8" |

---

## 5. Window Envelope Clearances (#10 Double-Window)

When the IMb shows through the window:

| Requirement | Value |
|---|---|
| From bottom edge of window | ≥ 3/16" |
| From left/right edges of window | ≥ 1/8" |
| From top edge of window | ≥ 1/25" |

> **Note:** Window bottom at 5/8" from envelope bottom + 3/16" window clearance puts the IMb ~13/16"+ from envelope bottom, satisfying the ≥ 5/8" envelope rule.

---

## 6. CSS / HTML Print Considerations

```css
@font-face {
  font-family: 'USPSIMBStandard';
  src: url('/fonts/USPSIMBStandard.ttf') format('truetype');
}

.imb-barcode {
  font-family: 'USPSIMBStandard', monospace;
  font-size: 12pt; /* Do NOT change — controls bar density */
  line-height: 1;
  letter-spacing: 0;
  -webkit-font-smoothing: none;
  font-smooth: never; /* Prevent antialiasing from breaking scans */
  color: #000;
}
```

```html
<!-- Rendered below city/state/zip in your address block -->
<div class="imb-barcode">{{ encodedImb }}</div>
```

---

## 7. Serial Number Strategy

- Store an **auto-incrementing integer** per MID in your database
- Zero-pad to 6 digits on output (`000001`, `000002`, etc.)
- For Basic service, uniqueness is not strictly enforced — but incrementing gives cleaner IV tracking data
- No 45-day reuse restriction for Basic (that's Full-Service only)

**Example migration:**
```php
Schema::create('imb_serials', function (Blueprint $table) {
    $table->id();
    $table->string('mid', 9);
    $table->unsignedInteger('last_serial')->default(0);
    $table->timestamps();
});
```

---

## 8. BCG / MID Registration

1. Go to [gateway.usps.com](https://gateway.usps.com)
2. Sign up for a free BCG account
3. You'll be assigned a **MID** (9 digits) and **CRID** automatically
4. From the main menu: **Mailing Services → Informed Visibility → Get Access**
5. Access granted immediately

> Call USPS BCG support during business hours if the portal gives you trouble: **1-800-238-3150, Option #2**

---

## 9. Build Order (Before MID)

You can build everything except generating real barcodes:

- [ ] IMb encoding algorithm (digit string → 65-char string)
- [ ] Font embedding and CSS layout
- [ ] Serial number DB table + increment logic
- [ ] Address block layout with IMb placement
- [ ] Print stylesheet with correct pt sizing

Plug in your MID once obtained — everything else is ready to go.

---

## 10. Reference Links

- [USPS IMb PostalPro page](https://postalpro.usps.com/mailing/intelligent-mail-barcode)
- [USPS IMb Spec (USPS-B-3200)](https://postalpro.usps.com/mailing/intelligent-mail-barcode)
- [BCG Registration](https://gateway.usps.com)
- [IV-MTR API Developer Toolkit](https://postalpro.usps.com/informedvisibility)
- [TCGTracking.com](https://tcgtracking.com) — reference implementation for TCG sellers
- [USPS IMb Encoder/Decoder](https://postalpro.usps.com/tools/encoder)