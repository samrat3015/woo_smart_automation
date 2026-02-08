# 📦 Woo Smart Automation — Scalable Architecture Plan

This document outlines the **Modular Architecture** designed to support many future features (License, Courier, SMS, etc.) without cluttering the codebase.

---

## 🏗 Scalable Folder Structure

We will use a **Module-based** architecture. Each feature (like "Incomplete Order") will live in its own isolated folder inside `includes/Modules/`.

```text
woo-smart-automation/
├── woo-smart-automation.php       # Main Plugin File (Entry Point)
├── uninstall.php                  # Cleanup on deletion
│
├── includes/
│   ├── Core/                      # ⚙️ Core System (Base Logic)
│   │   ├── Plugin.php             # Main Class (The "Brain")
│   │   ├── Loader.php             # Hooks Manager
│   │   ├── Database.php           # Table Creator
│   │   ├── Assets.php             # Enqueue JS/CSS
│   │   └── Ajax.php               # Central AJAX Router
│   │
│   ├── Modules/                   # � FEATURES (Add new folders here)
│   │   ├── BaseModule.php         # Abstract Class for Modules
│   │   │
│   │   ├── IncompleteOrder/       # Feature 1
│   │   │   ├── IncompleteOrder.php
│   │   │   ├── CaptureService.php
│   │   │   └── OrderHook.php
│   │   │
│   │   ├── Courier/               # Feature 2 (Future)
│   │   │   ├── CourierManager.php
│   │   │   └── WebhookHandler.php
│   │   │
│   │   └── License/               # Feature 3 (Future)
│   │       ├── LicenseManager.php
│   │       └── Validator.php
│   │
│   └── Admin/                     # 🖥️ Dashboard UI
│       ├── AdminMenu.php
│       └── Settings.php
│
├── assets/
│   ├── js/
│   │   ├── public.js
│   │   └── admin.js
│   └── css/
│
└── languages/
```

---

## 🚀 Why this structure?

1.  **Isolation:** If "Courier" breaks, "Incomplete Order" still works.
2.  **Scalability:** Want to add "SMS Notification"? Just create `includes/Modules/SmsNotify/`.
3.  **Maintenance:** You always know where to look. Logic is not scattered in one huge file.

---

## � Implementation Phase 1: Setup & Incomplete Order

We will create the skeleton and the first module now.

### Step 1: Create Directories

We will execute commands to build this folder tree.

### Step 2: `woo-smart-automation.php`

The main file that simply requires `includes/Core/Plugin.php` and runs it.

### Step 3: `includes/Core/Plugin.php`

This class will load the `Modules` automatically.

### Step 4: `includes/Modules/IncompleteOrder/`

We will implement the abandoned cart capture logic here.

---

## �️ Database Schema (Global for Modules)

Typically each module might need a table.
`IncompleteOrder` will use `wp_woo_smart_incomplete_orders`.

```sql
CREATE TABLE wp_woo_smart_incomplete_orders (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    session_token varchar(100) NOT NULL,
    phone varchar(20) NOT NULL,
    name varchar(100) NULL,
    cart_data longtext NULL,
    status varchar(20) DEFAULT 'captured',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY phone (phone)
) $charset_collate;
```
