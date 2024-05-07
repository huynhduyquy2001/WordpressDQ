<?php
include_once (plugin_dir_path(__FILE__) . 'includes/function.php');
add_action('woocommerce_checkout_order_processed', 'send_novu_webhook_on_order_success');