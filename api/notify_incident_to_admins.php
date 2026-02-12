<?php
/**
 * Integration Hook for Incident Report Module
 * Call this after inserting a new incident with NLP data
 * 
 * Usage in Incident_report.php:
 * require_once 'notify_incident_to_admins.php';
 * notifyAdminsOfNewIncident($incident_data);
 */

require_once __DIR__ . '/notify_admins_nlp.php';

function notifyAdminsOfNewIncident($incident_data) {
    return \IncidentNLPNotifier::notifyAdminsOfIncident($incident_data);
}

?>
