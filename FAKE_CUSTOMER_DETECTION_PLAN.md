# Plan: Fake Customer Detection & Trust Scoring System

This plan outlines the implementation of a risk scoring system for WooCommerce orders to identify suspicious customers based on various behavioral signals.

## 🎯 Objectives
- Analyze customer behavior across multiple signals.
- Generate a Risk Score (0–100) for each order/customer.
- Provide human-readable summaries of positive and negative signals.
- Integrate color-coded scores and details into the WooCommerce Orders admin interface.

## 🧠 Logic & Scoring

### ❌ Negative Signals (Increase Score)
- **Invalid Phone Format**: Check if the phone number matches expected patterns (e.g., length, digits). (+20 points)
- **Cancelled/Failed Orders**: Count previous orders with `cancelled` or `failed` status. (+15 points per order, max 60)
- **Courier Returns**: Detect if previous orders were returned (based on status mapping or order notes). (+30 points per return)
- **IP Reuse on Failures**: Check if the current IP has multiple failed/cancelled orders. (+25 points)
- **High Cancellation Rate**: If > 50% of total orders are cancelled (min 3 orders). (+20 points)

### ✅ Positive Signals (Reduce Score)
- **Successful Deliveries**: Count previous `completed` orders. (-20 points per order)
- **Stable History**: If the customer has been active for > 6 months with successful orders. (-15 points)
- **Verified Phone**: If the phone number was previously used in a completed order. (-10 points)
- **Low Return Rate**: If returns are 0. (-10 points)

*Note: Final score will be clamped between 0 and 100.*

## 📂 Proposed File Structure
- `includes/Modules/FakeCustomerDetection/FakeCustomerDetection.php`: Main module class for initialization.
- `includes/Modules/FakeCustomerDetection/RiskScorer.php`: core logic for calculating scores and generating summaries.
- `includes/Modules/FakeCustomerDetection/AdminIntegration.php`: Handles WooCommerce Orders table columns and tooltips.
- `assets/js/risk-score.js`: (Optional) For handling any modal/tooltip interactions if needed beyond CSS.
- `assets/css/risk-score.css`: Styling for the color-coded scores (Green, Yellow, Red).

## 🛠️ Implementation Steps

### 1. Module Initialization
- Create the `FakeCustomerDetection` directory.
- Register the module in `includes/Core/Plugin.php`.
- Add a setting in `AdminMenu.php` to enable/disable the module.

### 2. Risk Scorer Logic
- Implement `RiskScorer` class.
- Method `calculate_score( $order_id )`:
    - Fetch order data (phone, IP, customer ID).
    - Query database for customer history.
    - Apply signal weights.
    - Returns an array: `[ 'score' => 85, 'signals' => [ 'negative' => [...], 'positive' => [...] ] ]`.

### 3. Data Storage
- Store risk results in Order Meta (`_wsa_risk_score`, `_wsa_risk_signals`).
- Trigger calculation on `woocommerce_new_order` and `woocommerce_order_status_changed`.

### 4. Admin Integration
- Use `manage_edit-shop_order_columns` (or `manage_woocommerce_page_wc-orders_columns` for HPOS) to add the "Risk Score" column.
- Use `manage_edit-shop_order_custom_column` to display the score and summary.
- Add CSS to colorize the score:
    - 0-30: Green (Low Risk)
    - 31-70: Yellow (Medium Risk)
    - 71-100: Red (High Risk)

### 5. Detailed View
- Implement a tooltip or a simple "view details" link that reveals the breakdown of signals.
- **Courier History Integration**: Within the detailed view, list specific courier-related events (e.g., "Pathao: Returned", "Steadfast: Delivered") found in the customer's order history.

## 📝 Example Summary Output
**Score: 85 (High Risk)**
- ❌ 5 previous cancelled orders found.
- ❌ Invalid phone number format detected.
- ✅ No courier returns found.
