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
];
