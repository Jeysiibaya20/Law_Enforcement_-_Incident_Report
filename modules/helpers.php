<?php
function render_status($status) {
    return match ($status) {
        'Pending' => '<span class="badge bg-warning">Pending</span>',
        'Approved' => '<span class="badge bg-primary">Approved</span>',
        'Under Investigation' => '<span class="badge bg-info">Under Investigation</span>',
        'Resolved' => '<span class="badge bg-success">Resolved</span>',
        default => '<span class="badge bg-secondary">Archived</span>',
    };
}

function render_priority($priority) {
    return match ($priority) {
        'High' => '<span class="badge bg-danger">High</span>',
        'Medium' => '<span class="badge bg-warning">Medium</span>',
        default => '<span class="badge bg-info">Low</span>',
    };
}

// Backwards-compatible aliases used by some templates
function render_status_badge($status) {
    return render_status($status);
}

function render_priority_badge($priority) {
    return render_priority($priority);
}
