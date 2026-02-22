<?php
if (!function_exists('render_status')) {
    function render_status($status) {
        // Use PHP 8 match for concise mapping; fallback to a neutral badge for unknown
        return match ($status) {
            'Pending' => '<span class="badge bg-warning">Pending</span>',
            'Approved' => '<span class="badge bg-primary">Approved</span>',
            'Under Investigation' => '<span class="badge bg-info">Under Investigation</span>',
            'Resolved' => '<span class="badge bg-success">Resolved</span>',
            default => '<span class="badge bg-secondary">Archived</span>',
        };
    }
}

if (!function_exists('render_priority')) {
    function render_priority($priority) {
        return match ($priority) {
            'High' => '<span class="badge bg-danger">High</span>',
            'Medium' => '<span class="badge bg-warning">Medium</span>',
            default => '<span class="badge bg-info">Low</span>',
        };
    }
}

// Backwards-compatible aliases used by some templates
if (!function_exists('render_status_badge')) {
    function render_status_badge($status) {
        return render_status($status);
    }
}

if (!function_exists('render_priority_badge')) {
    function render_priority_badge($priority) {
        return render_priority($priority);
    }
}
