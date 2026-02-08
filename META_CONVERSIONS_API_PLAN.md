# Meta (Facebook/Instagram) Conversions API - Implementation Plan

## Overview

The Meta Conversions API (CAPI) allows sending web events directly from the server to Meta. This bypasses browser-side issues like ad blockers and tracking preventions, ensuring more accurate data for Meta Ads.

## 1. Technical Requirements

- Meta Pixel ID
- Meta Conversions API Access Token
- PHP CURL support (standard in WordPress)
- Meta Business Suite access

## 2. Core Components to Implement

### A. Settings Configuration

We need a settings page (or a section in the existing settings) to store:

- `pixel_id`
- `access_token`
- `test_event_code` (useful for debugging via Meta Test Events tool)
- `enable_capi` toggle

### B. Event Capturing Logic

We will hook into WooCommerce events:

- **`woocommerce_add_to_cart`** -> `AddToCart`
- **`woocommerce_checkout_process`** -> `InitiateCheckout`
- **`woocommerce_thankyou`** -> `Purchase`
- **`wp_head`** (for generic page views) -> `ViewContent` (optional, for specific products)

### C. Server-Side Sender Service

A dedicated service class to handle the POST requests to Meta's Graph API:
`https://graph.facebook.com/v18.0/{PID}/events?access_token={TOKEN}`

### D. User Data Hashing

Meta requires user data (email, phone, name) to be SHA-256 hashed before sending.

## 3. Implementation Steps

1. **Create Module Structure**:
   - `includes/Modules/MetaCAPI/MetaCAPI.php` (Main entry point)
   - `includes/Modules/MetaCAPI/EventDispatcher.php` (Hooks logic)
   - `includes/Modules/MetaCAPI/ApiService.php` (CURL requests)

2. **Register Module**:
   Add the MetaCAPI module to the core `Plugin.php` loader.

3. **Develop Logic**:
   - Capture customer data from the session/order.
   - Format the payload according to [Meta Event Parameters](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters).
   - Send data asynchronously or at the end of the request to prevent slowing down the site.

4. **Testing**:
   - Use the Pixel Helper browser extension.
   - Use the Meta Test Events tool with `test_event_code`.

## 4. Documentation for WordPress Admin

The documentation will be available in the plugin's dashboard to guide users on how to generate an Access Token from Meta Business Suite.
