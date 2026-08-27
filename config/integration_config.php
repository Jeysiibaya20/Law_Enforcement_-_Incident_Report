<?php
/**
 * Dynamic Integration Configuration & Settings Helper
 * Centralized registry for external system API URLs, authentication headers, and dispatch logic.
 */

if (!defined('ALERTARA_INTEGRATION_CONFIG_LOADED')) {
    define('ALERTARA_INTEGRATION_CONFIG_LOADED', true);

    require_once __DIR__ . '/db_connect.php';

    /**
     * Default integration endpoint registry
     */
    function getDefaultIntegrationSettings(): array {
        return [
            'cctv_request_api_url' => 'https://policy.alertaraqc.com/api/cctv_requests_receive.php',
            'tftr_violation_api_url' => 'https://tftr.alertaraqc.com/api/violations/violation_report_api.php',
            'group7_inspection_api_url' => 'https://inspection.alertaraqc.com/api/documents/request',
            'group5_crime_map_api_url' => 'https://crimemap.alertaraqc.com/api/update_heatmap.php',
            'group3_resource_api_url' => 'https://dispatch.alertaraqc.com/api/assign_officer.php',
            'campaign_api_url' => 'https://campaign.alertaraqc.com/api/v1/campaigns/public',
            'external_api_secret' => getenv('EXTERNAL_API_SECRET') ?: '',
            'auto_dispatch_cctv' => '0',
            'auto_dispatch_all_modules' => '0'
        ];
    }

    /**
     * Ensure database table for system integration settings exists
     */
    function ensureIntegrationSettingsSchema(?PDO $pdo = null): void {
        if (!$pdo) {
            $pdo = getDBConnection();
        }
        if (!($pdo instanceof PDO)) return;

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_integration_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT NULL,
                description VARCHAR(255) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed defaults if empty
            $defaults = getDefaultIntegrationSettings();
            $stmtCheck = $pdo->prepare("SELECT setting_key FROM system_integration_settings WHERE setting_key = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO system_integration_settings (setting_key, setting_value) VALUES (?, ?)");

            foreach ($defaults as $key => $val) {
                $stmtCheck->execute([$key]);
                if (!$stmtCheck->fetch()) {
                    $stmtInsert->execute([$key, $val]);
                }
            }
        } catch (Exception $e) {
            error_log("Notice: system_integration_settings schema setup notice: " . $e->getMessage());
        }
    }

    /**
     * Fetch a specific integration setting by key
     */
    function getIntegrationSetting(string $key, ?string $default = null): string {
        $defaults = getDefaultIntegrationSettings();
        $fallback = $default ?? ($defaults[$key] ?? '');

        try {
            $pdo = getDBConnection();
            if ($pdo instanceof PDO) {
                ensureIntegrationSettingsSchema($pdo);
                $stmt = $pdo->prepare("SELECT setting_value FROM system_integration_settings WHERE setting_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $val = $stmt->fetchColumn();
                if ($val !== false && $val !== null && trim($val) !== '') {
                    return trim($val);
                }
            }
        } catch (Exception $e) {}

        // Fallback to env or constant
        $envVal = getenv(strtoupper($key));
        if ($envVal !== false && trim($envVal) !== '') {
            return trim($envVal);
        }

        return $fallback;
    }

    /**
     * Save/update a specific integration setting
     */
    function setIntegrationSetting(string $key, string $value): bool {
        try {
            $pdo = getDBConnection();
            if ($pdo instanceof PDO) {
                ensureIntegrationSettingsSchema($pdo);
                $stmt = $pdo->prepare("INSERT INTO system_integration_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                return $stmt->execute([$key, trim($value)]);
            }
        } catch (Exception $e) {
            error_log("Failed to save integration setting {$key}: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Fetch all integration settings as key-value map
     */
    function getAllIntegrationSettings(): array {
        $settings = getDefaultIntegrationSettings();
        try {
            $pdo = getDBConnection();
            if ($pdo instanceof PDO) {
                ensureIntegrationSettingsSchema($pdo);
                $rows = $pdo->query("SELECT setting_key, setting_value FROM system_integration_settings")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    if (isset($r['setting_key'])) {
                        $settings[$r['setting_key']] = $r['setting_value'] ?? '';
                    }
                }
            }
        } catch (Exception $e) {}

        return $settings;
    }

    /**
     * Dispatch payload via cURL HTTP POST to any configured target URL
     */
    function dispatchPayloadToEndpoint(string $targetUrl, array $payload, array $customHeaders = [], int $timeout = 25): array {
        if (empty($targetUrl)) {
            throw new Exception("No target endpoint URL configured.");
        }

        $secret = getIntegrationSetting('external_api_secret');
        $effectiveSecret = !empty($secret) ? $secret : 'ALERTARA-EMERGENCY-2026';

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Partner-Client: AlertaraQC-Incident-System/2.0',
            'X-API-KEY: ' . $effectiveSecret,
            'X-API-Key: ' . $effectiveSecret,
            'Authorization: Bearer ' . $effectiveSecret,
            'X-External-Secret: ' . $effectiveSecret,
            'X-Webhook-Secret: ' . $effectiveSecret
        ], $customHeaders);

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'http_code' => 0,
                'endpoint' => $targetUrl,
                'message' => 'cURL PHP extension is not enabled on this server. Payload prepared.',
                'payload_sent' => $payload
            ];
        }

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        if (is_resource($ch) || (is_object($ch) && $ch instanceof \CurlHandle)) {
            @curl_close($ch);
        }

        $decodedResponse = json_decode($rawResponse, true) ?: ['raw_response' => $rawResponse];
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'endpoint' => $targetUrl,
            'response' => $decodedResponse,
            'curl_error' => $curlError ?: null
        ];
    }
}
