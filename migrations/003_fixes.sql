-- Migration 003: Security fixes
-- Fix P4: rate_limits missing UNIQUE KEY (ON DUPLICATE KEY UPDATE never fired)
ALTER TABLE rate_limits
    ADD UNIQUE KEY uq_rate_limits_ip_endpoint_window (ip, endpoint, window_start);
