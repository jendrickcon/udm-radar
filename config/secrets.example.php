<?php
/**
 * config/secrets.example.php — Template for Local Secrets Configuration
 *
 * INSTRUCTIONS:
 * 1. Copy this file to config/secrets.local.php (which is ignored by Git).
 * 2. Set UDM_RADAR_ML_SECRET to a cryptographically secure random token.
 * 3. In production, prefer setting the UDM_RADAR_ML_SECRET environment variable
 *    in the web server configuration or system environment instead of disk files.
 *
 * NOTE: Never commit config/secrets.local.php or real secrets to version control.
 */

return [
    // Shared secret for authenticating administrative ML governance actions
    // (candidate model training, candidate discard, model promotion).
    // Leave blank or null if setting via server environment variable.
    'UDM_RADAR_ML_SECRET' => '',
];
