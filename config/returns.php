<?php

return [
    // Deliberately disabled until the migrations and financial rules are reviewed.
    // No environment-variable shortcut enables a production refund rollout.
    'backend_enabled' => false,
    // Simplified review (new Seller review and historical Admin review) includes
    // virtual refund accounting, never enables FINANCIAL processing.
    'admin_review_enabled' => false,
    // Separate rollout approval: COD/complaint reconciliation must not activate
    // merely because schema was deployed or refund processing was configured.
    'earning_release_enabled' => false,
    // A delivered item may be submitted for review only within this period.
    // This is deliberately configuration, not an environment override.
    'request_window_days' => 7,
    // Display-only: Seller is responsible for return shipping, but no wallet or
    // payment record is created by the physical-return workflow.
    'virtual_return_shipping_fee_minor' => 0,
];
