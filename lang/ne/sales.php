<?php

declare(strict_types=1);

return [
    // General
    'sales' => 'बिक्री',
    'new_sale' => 'नयाँ बिक्री',
    'sale' => 'बिक्री',
    'invoice_no' => 'बिल नम्बर',
    'status' => 'स्थिति',
    'date' => 'मिति',
    'customer' => 'ग्राहक',
    'subtotal' => 'उपजम्मा',
    'discount' => 'छुट',
    'tax' => 'कर',
    'grand_total' => 'कुल जम्मा',
    'paid' => 'तिरेको',
    'due' => 'बाँकी',
    'notes' => 'टिप्पणी',

    // Status labels
    'status_draft' => 'मस्यौदा',
    'status_posted' => 'पोस्ट भएको',
    'status_cancelled' => 'रद्द',

    // POS screen
    'pos_title' => 'बिक्री स्थल (POS)',
    'search_products' => 'नाम वा SKU द्वारा उत्पादन खोज्नुहोस्...',
    'cart' => 'कार्ट',
    'cart_empty' => 'कार्ट खाली छ। उत्पादन थप्नुहोस्।',
    'qty' => 'मात्रा',
    'price' => 'एकाइ मूल्य',
    'line_total' => 'जम्मा',
    'remove_item' => 'हटाउनुहोस्',
    'clear_cart' => 'कार्ट खाली गर्नुहोस्',
    'payment' => 'भुक्तानी',
    'payment_method' => 'भुक्तानी विधि',
    'amount_paid' => 'तिरेको रकम',
    'change' => 'फिर्ता',
    'post_sale' => 'बिक्री सम्पन्न गर्नुहोस्',
    'save_draft' => 'मस्यौदा बचत गर्नुहोस्',
    'select_customer' => 'ग्राहक छान्नुहोस् (ऐच्छिक)',
    'walk_in' => 'प्रत्यक्ष ग्राहक',

    // Payment methods
    'cash' => 'नगद',
    'bank' => 'बैंक ट्रान्सफर',
    'qr' => 'QR / eSewa / Khalti',

    // Sales list
    'all_sales' => 'सबै बिक्री',
    'search_sales' => 'बिल नम्बर वा ग्राहक खोज्नुहोस्...',
    'no_sales' => 'कुनै बिक्री फेला परेन।',

    // Invoice
    'invoice' => 'बिल',
    'bill_to' => 'खाता',
    'seller' => 'विक्रेता',
    'issued_on' => 'जारी मिति',
    'sold_by' => 'बेचेको व्यक्ति',
    'print_invoice' => 'बिल छाप्नुहोस्',
    'thermal_print' => 'थर्मल छाप (80mm)',

    // Returns
    'returns' => 'फिर्ता',
    'return_sale' => 'बिक्री फिर्ता',
    'return_items' => 'फिर्ता गर्ने वस्तुहरू',
    'return_reason' => 'फिर्ताको कारण',
    'process_return' => 'फिर्ता प्रक्रिया गर्नुहोस्',
    'return_success' => 'फिर्ता सफलतापूर्वक प्रक्रिया गरियो।',

    // Customers
    'customers' => 'ग्राहकहरू',
    'new_customer' => 'नयाँ ग्राहक',
    'customer_name' => 'ग्राहकको नाम',
    'phone' => 'फोन',
    'address' => 'ठेगाना',
    'outstanding' => 'बाँकी रकम',
    'no_customers' => 'कुनै ग्राहक फेला परेन।',

    // Messages
    'sale_posted' => 'बिक्री सफलतापूर्वक सम्पन्न भयो।',
    'sale_cancelled' => 'बिक्री रद्द गरियो।',
    'draft_saved' => 'मस्यौदा बचत गरियो।',
    'insufficient_stock' => ':product को लागि पर्याप्त स्टक छैन।',
    'customer_archived' => 'ग्राहक :name सफलतापूर्वक अभिलेख गरियो।',

    // POS enhancements
    'search_hint' => 'नाम, SKU वा बारकोड टाइप गर्नुहोस्',
    'new_sale_fab' => 'नयाँ बिक्री',
    'cart_empty_alert' => 'कृपया कम्तिमा एउटा सामान थप्नुहोस्।',
    'posting' => 'पेश गर्दै...',
    'sale_error' => 'बिक्री पूरा गर्न सकिएन। कृपया पुनः प्रयास गर्नुहोस्।',
    'network_error' => 'नेटवर्क त्रुटि। जडान जाँच गर्नुहोस्।',
    'network_timeout' => 'अनुरोध समय सकियो।',
    'retry_same_key' => 'पुनः प्रयास गर्न Retry थिच्नुहोस् (दोहोरो बिक्री हुँदैन)।',
];
