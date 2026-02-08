# SteadFast Courier Score Integration Plan

## Overview
Integrate SteadFast's fraud check API to enhance the Fake Customer Detection module by adding courier success rate data to the risk scoring system.

---

## 1. API Integration

### **API Endpoint**
```
GET https://portal.packzy.com/api/v1/fraud_check/{phone_number}
```

### **Headers Required**
```php
'content-type' => 'application/json',
'api-key'      => 'Your SteadFast API Key',
'secret-key'   => 'Your SteadFast Secret Key'
```

### **URL Parameter**
- `phone_number` - Customer phone number (digits only, no formatting)

### **Response Example**
```json
{
    "status": 200,
    "total_parcels": 6,
    "total_delivered": 6,
    "total_cancelled": 0,
    "current": 5,  // API calls used today
    "limit": 100,  // Daily API call limit
    "error": null  // or error message if limit exceeded
}
```

### **Success Rate Calculation**
```php
success_rate = (total_delivered / total_parcels) * 100
// Example: (6 / 6) * 100 = 100%
```

---

## 2. Implementation Steps

### **Step 1: Add SteadFast API Settings**

**File:** `woo-smart-automation/includes/Modules/Courier/Courier.php`

Add new settings for SteadFast fraud check:
- ✅ Enable/Disable fraud check
- 🔑 API Key field
- 🔐 Secret Key field
- ⚙️ Cache duration (to prevent excessive API calls)

**Settings Location:** Admin → Smart Automation → Courier Settings

---

### **Step 2: Create Courier Score Service**

**New File:** `woo-smart-automation/includes/Modules/Courier/CourierScoreService.php`

**Methods:**
```php
class CourierScoreService {
    
    /**
     * Get customer courier score from SteadFast
     * @param string $phone_number
     * @return array|false
     */
    public function get_courier_score($phone_number);
    
    /**
     * Calculate success rate percentage
     * @param int $total_parcels
     * @param int $total_delivered
     * @return float
     */
    public function calculate_success_rate($total_parcels, $total_delivered);
    
    /**
     * Check if score is cached
     * @param string $phone_number
     * @return array|false
     */
    private function get_cached_score($phone_number);
    
    /**
     * Cache the score data
     * @param string $phone_number
     * @param array $data
     */
    private function cache_score($phone_number, $data);
}
```

**Caching Strategy:**
- Use WordPress transients
- Cache key: `wsa_courier_score_{phone_hash}`
- Duration: 24 hours (configurable)
- Reduces API calls for repeat customers

---

### **Step 3: Update Risk Scorer**

**File:** `woo-smart-automation/includes/Modules/FakeCustomerDetection/RiskScorer.php`

#### **Add New Risk Factor: Courier Success Rate**

**Risk Scoring Logic:**

| Success Rate | Risk Score | Risk Level |
|-------------|------------|------------|
| 90% - 100%  | -20 points | Low Risk ✅ |
| 70% - 89%   | 0 points   | Neutral ⚠️ |
| 50% - 69%   | +15 points | Medium Risk ⚠️ |
| 30% - 49%   | +30 points | High Risk 🔴 |
| 0% - 29%    | +50 points | Very High Risk 🔴 |
| No data     | +5 points  | Unknown (slight penalty) |

**Additional Factors:**

| Metric | Condition | Risk Score |
|--------|-----------|------------|
| Total Cancelled | > 5 orders | +10 points |
| Total Cancelled | > 10 orders | +25 points |
| Cancelled Ratio | > 50% of total | +20 points |
| No Order History | 0 parcels | +5 points (new customer) |

**Updated calculate_risk_score() method:**
```php
public function calculate_risk_score($order) {
    $risk_score = 0;
    
    // Existing checks...
    $risk_score += $this->check_email_validity($order);
    $risk_score += $this->check_phone_validity($order);
    $risk_score += $this->check_customer_history($order);
    
    // NEW: Courier success rate check
    $risk_score += $this->check_courier_success_rate($order);
    
    return min($risk_score, 100); // Cap at 100
}

private function check_courier_success_rate($order) {
    $courier_service = new CourierScoreService();
    $phone = $order->get_billing_phone();
    
    $score_data = $courier_service->get_courier_score($phone);
    
    if (!$score_data || isset($score_data['error'])) {
        return 5; // No data penalty
    }
    
    $success_rate = $courier_service->calculate_success_rate(
        $score_data['total_parcels'],
        $score_data['total_delivered']
    );
    
    $risk = 0;
    
    // Success rate scoring
    if ($success_rate >= 90) {
        $risk = -20; // Reward good customers
    } elseif ($success_rate >= 70) {
        $risk = 0;
    } elseif ($success_rate >= 50) {
        $risk = 15;
    } elseif ($success_rate >= 30) {
        $risk = 30;
    } else {
        $risk = 50;
    }
    
    // Additional penalties
    if ($score_data['total_cancelled'] > 10) {
        $risk += 25;
    } elseif ($score_data['total_cancelled'] > 5) {
        $risk += 10;
    }
    
    // Store for display
    update_post_meta($order->get_id(), '_courier_success_rate', $success_rate);
    update_post_meta($order->get_id(), '_courier_total_orders', $score_data['total_parcels']);
    update_post_meta($order->get_id(), '_courier_delivered', $score_data['total_delivered']);
    update_post_meta($order->get_id(), '_courier_cancelled', $score_data['total_cancelled']);
    
    return $risk;
}
```

---

### **Step 4: Update Admin UI**

**File:** `woo-smart-automation/assets/css/risk-score.css`

Add styles for courier score display:
```css
.courier-score-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}

.courier-score-high { background: #4caf50; color: white; }
.courier-score-medium { background: #ff9800; color: white; }
.courier-score-low { background: #f44336; color: white; }

.courier-stats-modal {
    /* Similar to steadfast-api modal styles */
}
```

**File:** `woo-smart-automation/includes/Modules/FakeCustomerDetection/AdminIntegration.php`

Add courier score column to orders table:
```php
add_filter('manage_shop_order_posts_columns', function($columns) {
    $columns['courier_score'] = __('📦 Courier Score', 'woo-smart-automation');
    return $columns;
}, 20);

add_action('manage_shop_order_posts_custom_column', function($column, $order_id) {
    if ($column === 'courier_score') {
        $success_rate = get_post_meta($order_id, '_courier_success_rate', true);
        
        if ($success_rate !== '') {
            $badge_class = 'courier-score-high';
            if ($success_rate < 70) $badge_class = 'courier-score-medium';
            if ($success_rate < 50) $badge_class = 'courier-score-low';
            
            echo sprintf(
                '<button class="show-courier-details" data-order-id="%d">
                    <span class="courier-score-badge %s">%s%%</span>
                </button>',
                $order_id,
                $badge_class,
                $success_rate
            );
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}, 20, 2);
```

---

### **Step 5: Add Modal Display**

**File:** `woo-smart-automation/assets/js/risk-score.js`

Add AJAX handler to show courier details:
```javascript
jQuery(document).on('click', '.show-courier-details', function(e) {
    e.preventDefault();
    
    var orderId = jQuery(this).data('order-id');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wsa_get_courier_details',
            order_id: orderId,
            nonce: wsa_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                var data = response.data;
                var content = `
                    <div class="courier-stats-modal">
                        <h2>📊 SteadFast Success Rate</h2>
                        <p><strong>📦 Total Orders:</strong> ${data.total_parcels || 0}</p>
                        <p><strong>✅ Total Delivered:</strong> ${data.total_delivered || 0}</p>
                        <p><strong>❌ Total Cancelled:</strong> ${data.total_cancelled || 0}</p>
                        <p><strong>📈 Success Rate:</strong> ${data.success_rate}%</p>
                    </div>
                `;
                // Show modal (implement modal display logic)
                showModal(content);
            }
        }
    });
});
```

**AJAX Handler in PHP:**
```php
add_action('wp_ajax_wsa_get_courier_details', function() {
    check_ajax_referer('wsa_ajax_nonce', 'nonce');
    
    $order_id = intval($_POST['order_id']);
    
    $data = [
        'total_parcels' => get_post_meta($order_id, '_courier_total_orders', true),
        'total_delivered' => get_post_meta($order_id, '_courier_delivered', true),
        'total_cancelled' => get_post_meta($order_id, '_courier_cancelled', true),
        'success_rate' => get_post_meta($order_id, '_courier_success_rate', true),
    ];
    
    wp_send_json_success($data);
});
```

---

### **Step 6: Update Risk Score Display**

**File:** `woo-smart-automation/includes/Modules/FakeCustomerDetection/AdminIntegration.php`

Update risk breakdown to include courier score:
```php
private function get_risk_breakdown($order_id) {
    $breakdown = [];
    
    // Existing factors...
    
    // New: Courier success rate
    $success_rate = get_post_meta($order_id, '_courier_success_rate', true);
    if ($success_rate !== '') {
        $courier_risk = $this->calculate_courier_risk($success_rate);
        $breakdown[] = [
            'factor' => 'Courier Success Rate',
            'score' => $courier_risk,
            'details' => sprintf('%s%% success rate', $success_rate)
        ];
    }
    
    return $breakdown;
}
```

---

## 3. Configuration & Settings

### **Admin Settings Panel**

**Location:** WP Admin → Smart Automation → Settings → Courier Integration

**Fields:**
1. ✅ **Enable SteadFast Fraud Check**
   - Checkbox to enable/disable feature

2. 🔑 **SteadFast API Key**
   - Text input (required if enabled)
   - Get from: https://portal.packzy.com

3. 🔐 **SteadFast Secret Key**
   - Password input (required if enabled)

4. ⏱️ **Cache Duration**
   - Select: 1 hour, 6 hours, 12 hours, 24 hours (default), 48 hours
   - Prevents excessive API calls

5. 🎯 **Apply to Orders**
   - All orders
   - Only high-risk orders (risk score > 50)
   - Manual check only

---

## 4. Database Schema

### **Post Meta Keys**

| Meta Key | Type | Description |
|----------|------|-------------|
| `_courier_success_rate` | float | Success rate percentage |
| `_courier_total_orders` | int | Total parcels from API |
| `_courier_delivered` | int | Successfully delivered |
| `_courier_cancelled` | int | Cancelled orders |
| `_courier_last_checked` | timestamp | Last API check time |

### **Transients**

| Transient Key | Expiration | Data |
|--------------|------------|------|
| `wsa_courier_score_{phone_hash}` | 24 hours | Full API response |

---

## 5. Error Handling

### **API Errors**

```php
// Unauthorized (wrong credentials)
if ($response['status'] === 401) {
    error_log('WSA: Invalid SteadFast API credentials');
    return false;
}

// Rate limit exceeded
if (isset($response['error']) && $response['error'] === 'limit_exceeded') {
    error_log('WSA: SteadFast API limit exceeded');
    // Use cached data if available
    return get_cached_score($phone_number);
}

// Network error
if (is_wp_error($response)) {
    error_log('WSA: Network error - ' . $response->get_error_message());
    return false;
}
```

---

## 6. Benefits

### **Improved Risk Detection**
- ✅ Real courier performance data
- ✅ Historical delivery success
- ✅ Fraud pattern detection
- ✅ Reduced manual review time

### **Better Customer Insights**
- 📊 See customer's delivery history
- 🎯 Identify reliable customers
- 🚫 Flag high-risk patterns early
- 💰 Reduce COD fraud losses

### **Enhanced UI**
- 📦 Quick visual indicators
- 📈 Detailed statistics modal
- 🔄 Auto-refresh capability
- 📝 Risk breakdown with courier data

---

## 7. Testing Checklist

- [ ] API credentials validation
- [ ] Phone number formatting (remove +880, spaces, etc.)
- [ ] Cache functionality
- [ ] Risk score calculation accuracy
- [ ] Modal display and close
- [ ] Column sorting in admin
- [ ] Bulk order processing
- [ ] Error handling for API failures
- [ ] Daily limit tracking
- [ ] Integration with existing risk factors

---

## 8. Files to Create/Modify

### **New Files:**
1. `includes/Modules/Courier/CourierScoreService.php`
2. `assets/css/courier-score.css`

### **Modify:**
1. `includes/Modules/Courier/Courier.php` - Add settings
2. `includes/Modules/FakeCustomerDetection/RiskScorer.php` - Add courier check
3. `includes/Modules/FakeCustomerDetection/AdminIntegration.php` - Add UI elements
4. `assets/js/risk-score.js` - Add modal logic
5. `includes/Core/Ajax.php` - Add AJAX handler

---

## 9. Future Enhancements

- 📊 **Analytics Dashboard** - Track fraud prevention savings
- 🔔 **Alert System** - Notify for suspicious patterns
- 📱 **Multiple Couriers** - Support Pathao, RedX, etc.
- 🤖 **Auto-Actions** - Auto-hold/cancel high-risk orders
- 📈 **Reporting** - Monthly fraud detection reports

---

## 10. Privacy & Compliance

⚠️ **Important Considerations:**
- API calls share customer phone numbers with SteadFast
- Add privacy notice in checkout/terms
- Store minimal data (only scores, not full API response)
- Respect GDPR/data protection laws
- Allow customers to request data deletion

---

## Next Steps

1. ✅ Review and approve this plan
2. 🔨 Implement CourierScoreService class
3. 🎨 Design admin UI components
4. 🧪 Test with real SteadFast API
5. 📝 Update plugin documentation
6. 🚀 Deploy to production

---

**Created:** February 2, 2026
**Plugin:** woo-smart-automation
**Feature:** SteadFast Courier Score Integration
