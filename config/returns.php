<?php

return [
    // Deliberately disabled until the migrations and financial rules are reviewed.
    // No environment-variable shortcut enables a production refund rollout.
    'backend_enabled' => false,
    // Separate rollout approval: COD/complaint reconciliation must not activate
    // merely because schema was deployed or refund processing was configured.
    'earning_release_enabled' => false,
    // A delivered item may be submitted for review only within this period.
    // This is deliberately configuration, not an environment override.
    'request_window_days' => 7,
];
