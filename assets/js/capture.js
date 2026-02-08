(function ($) {
  "use strict";

  let timer;
  const delay = 1000; // 1 second debounce

  // Universal selectors for Phone, Email, First Name, Last Name
  // Covers Classic and Block-based checkouts
  const fieldSelectors = [
    'input[name*="phone"]',
    'input[name*="email"]',
    'input[name*="first_name"]',
    'input[name*="last_name"]',
    'input#billing_phone',
    'input#billing_email',
    'input#billing_first_name',
    'input#billing_last_name',
    '.wc-block-components-checkout-step input'
  ].join(",");

  function captureData() {
    if (typeof wsa_ajax === 'undefined') {
      console.error("WSA: wsa_ajax is not defined. Script might not be localized correctly.");
      return;
    }

    // Attempt to find fields by partial name or ID matches
    const getVal = (keyword) => {
      // Priority: Specific name matches, then specific ID matches
      let val = $(`input[name*="${keyword}"], input[id*="${keyword}"]`).first().val();
      return val ? val.trim() : "";
    };

    const data = {
      action: "woo_smart_capture",
      nonce: wsa_ajax.nonce,
      phone: getVal("phone"),
      email: getVal("email"),
      first_name: getVal("first_name"),
      last_name: getVal("last_name"),
    };

    // Basic validation: need at least phone or email
    if (!data.phone && !data.email) {
      console.log("WSA: Skipping capture, phone and email are empty.");
      return;
    }

    console.log("WSA: Attempting to capture...", data);

    $.ajax({
       url: wsa_ajax.url,
       type: 'POST',
       data: data,
       success: function(response) {
         console.log("WSA Capture Success:", response);
       },
       error: function(xhr, status, error) {
         console.error("WSA Capture AJAX Error:", status, error, xhr.responseText);
       }
    });
  }

  $(document.body).on("input change blur", fieldSelectors, function (e) {
    if ($(this).val().length < 3 && e.type === "input") return; // Don't fire on every keystroke for 1-2 chars

    console.log("WSA Field interaction:", e.target.id || e.target.name || "unknown", e.type);
    
    clearTimeout(timer);
    
    if (e.type === "blur" || e.type === "change") {
      captureData();
    } else {
      timer = setTimeout(captureData, delay);
    }
  });
})(jQuery);
