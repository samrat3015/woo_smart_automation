# Courier Webhook Integration Plan

This document outlines the plan for integrating courier webhooks (Pathao & Steadfast) into the **Woo Smart Automation** plugin to automatically update WooCommerce order statuses.

## 1. Local Development Setup (Ngrok)

Since webhooks require a publicly accessible URL to send data to, we use **Ngrok** to create a secure tunnel from the internet to your local WordPress environment.

### Installation (Linux)

To install Ngrok on Linux, follow these steps:

1.  **Download & Install:**

    ```bash
    curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null && echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list && sudo apt update && sudo apt install ngrok
    ```

2.  **Authenticate:**
    Get your authtoken from the [Ngrok Dashboard](https://dashboard.ngrok.com/get-started/your-authtoken) and run:

    ```bash
    ngrok config add-authtoken YOUR_AUTH_TOKEN
    ```

3.  **Start Tunnel:**
    Assuming your local WordPress is running on port 80 or a specific local domain:

    ```bash
    ngrok http http://localhost
    # OR if using a custom local domain (e.g., plugincreate.local)
    ngrok http --host-header=plugincreate.local 80
    ```

4.  **Capture the URL:**
    Ngrok will provide a URL like `https://a1b2-c3d4.ngrok-free.app`. Use this URL as the base for your webhook endpoints.

---

## 2. Webhook Architecture

We will implement custom REST API endpoints in WordPress to receive data from couriers.

### Proposed Endpoints

- **Pathao:** `https://your-ngrok-url.app/wp-json/woo-smart-automation/v1/webhook/pathao`
- **Steadfast:** `https://your-ngrok-url.app/wp-json/woo-smart-automation/v1/webhook/steadfast`

---

## 3. Courier Specifics

### Pathao Webhook

- **Method:** POST
- **Verification:** Pathao usually sends a `X-Pathao-Signature` or requires a secret key verification.
- **Workflow:**
  - Receive payload.
  - Identify order by `consignment_id` or `order_id`.
  - Map Pathao status (e.g., `Delivered`) to WooCommerce status (e.g., `completed`).

### Steadfast Webhook

- **Method:** POST
- **Common Payload Fields:** `status`, `consignment_id`, `order_id`.
- **Workflow:** Similar to Pathao, verify the request and update the WooCommerce order meta and status.

---

## 4. Implementation Steps (Next Phase)

Following the existing plugin architecture, we will implement the courier integration as a separate module:

1.  **Create Module Directory:** `/includes/Modules/Courier/`
2.  **Base Class:** Create `Courier.php` to handle initialization and REST route registration.
3.  **Courier Sub-classes:** Create `Pathao.php` and `Steadfast.php` in a sub-folder to handle logic for each courier separately (following SOLID principles).
4.  **Register REST Routes:** Use `register_rest_route` within the `Courier` module to create the endpoints.
5.  **Request Validation:** Implement security checks (e.g., API keys, signatures) to ensure the request is actually from the courier.
6.  **Logger:** Add a logging system to record incoming webhook payloads in the WordPress debug log or a custom file.
7.  **Status Mapper:** Add settings in the admin dashboard (via `AdminMenu.php`) so users can map courier statuses to WooCommerce statuses.

---

## 5. Testing Guide

1.  **Start Ngrok:** Run `ngrok http --host-header=plugincreate.local 80` (adjust port/host if necessary).
2.  **Copy HTTPS URL:** Copy the URL (e.g., `https://a1b2.ngrok-free.app`).
3.  **Configure Courier:**
    - Go to Pathao/Steadfast Developer Dashboard.
    - Set Webhook URL: `https://[ngrok-id].ngrok-free.app/wp-json/woo-smart-automation/v1/webhook/[pathao|steadfast]`.
4.  **Trigger Test:** Use the "Send Test Webhook" feature in the courier dashboard.
5.  **Verify:** Check if the order status updates in WooCommerce or check logs.
