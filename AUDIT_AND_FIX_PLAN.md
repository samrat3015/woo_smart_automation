# Plugin Audit & Fix Plan - Woo Smart Shield

## Identified Problems

### 1. "Returning" Badge Inconsistency in Order Table
*   **Problem**: The "Returning (X)" badge shows incorrect or inconsistent counts. For example, showing "Returning (5)" for a new customer or changing from (5) to (3) for the same order.
*   **Root Cause**: The current logic includes orders with `processing` status in the count. If a customer has multiple active orders, they are counted against each other. Also, if guest orders are placed with the same email, they might be incorrectly aggregated.
*   **Fix**: 
    *   Limit the "Returning" count to `wc-completed` orders only. This ensures the badge truly reflects past successful purchases.
    *   Add a check to ensure we are matching the most unique identifier available (Customer ID > Phone > Email).

### 2. Risk Score "Analyzing..." & Manual Recheck
*   **Problem**: The Risk Score column often gets stuck on "Analyzing..." and lacks a manual way to trigger a refresh from the order table.
*   **Root Cause**: The `_wsa_risk_status` meta field isn't consistently updated to a 'finished' state after calculation, causing the UI to think it's still processing.
*   **Fix**:
    *   Ensure `_wsa_risk_status` is updated to `completed` after every calculation.
    *   Add a "Refresh/Recheck" icon button in the Risk Score column for manual updates.
    *   Fix the CSS/JS to prevent "Loading..." modal from appearing unnecessarily when data is already available.

### 3. Risk Score Discrepancy (Cross-Merchant vs Local)
*   **Problem**: FraudPeek might report "Low Risk" (external courier data), but the overall score shows "97 Critical" (internal scoring).
*   **Root Cause**: Local signals (like finding 8 cancelled orders) are assigned very high weights (up to +60 points). If these "cancelled" orders aren't filtered correctly (e.g., they were just test orders or failed payments), they incorrectly inflate the risk.
*   **Fix**:
    *   Adjust weights in `RiskScorer.php` to give more balance between FraudPeek (aggregated data) and local history.
    *   Improve `get_customer_orders` logic to distinguish between "Real" orders and failed/junk orders.
    *   Specifically handle the "Courier Cancelled" vs "Website Cancelled" distinction in the Risk Analysis modal.

### 5. Backend 500 Error during Recalculate
*   **Problem**: Clicking the 🔄 recalculate icon on the live server caused a "Connection error" (HTTP 500).
*   **Root Cause**: The previous recalculation logic was extremely heavy (O(N²) complexity). For every order belonging to a customer, it triggered a full re-score. If a customer had many orders, the server would run out of memory or time out.
*   **Fix**: 
    *   Optimized `ajax_recalculate_risk` to only perform the heavy scoring logic for the **current target order**.
    *   The results are then instantly synced to all other related orders via direct database updates, avoiding expensive recursive calculations.
    *   This ensures the recalculation is nearly instant even for long-term customers.

### 6. Modal Loading UX
*   **Problem**: The "Loading risk details..." modal was slow and blocked the entire screen.
*   **Root Cause**: General loading overlay was use before the AJAX request.
*   **Fix**:
    *   Refactored `risk-score.js` to show the modal frame **instantly** upon click.
    *   Added a specialized "Fetching Risk Intelligence..." spinner state inside the modal frame to provide immediate visual feedback.

## Implementation Plan

1.  **Update `CustomerSegmentation.php`**: Restrict "Returning" badge to completed orders.
2.  **Update `RiskScorer.php`**: Refine scoring algorithm and history lookup.
3.  **Update `AdminIntegration.php`**: Add recheck button, fix status labels, and improve modal data display.
4.  **Update `risk-score.js`**: Implement AJAX for the recheck button and optimize modal opening.
