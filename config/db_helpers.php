<?php
// config/db_helpers.php — Shared helper functions

/**
 * Auto-closes any drive whose last_date has passed.
 * Call this at the top of any page that lists or processes drives.
 */
function autoCloseDrives($conn): void {
    mysqli_query($conn,
        "UPDATE placement_drive
         SET drive_status = 'Closed'
         WHERE last_date < CURDATE()
           AND drive_status != 'Closed'"
    );
}

/**
 * Returns a drive's status string taking last_date into account.
 * 'Closed'       — last_date passed
 * 'Closing Soon' — last_date is within 3 days (inclusive)
 * 'Open'         — more than 3 days away
 */
function getDriveStatus(string $lastDate, string $currentStatus): string {
    if ($currentStatus === 'Closed') return 'Closed';
    $today = new DateTime('today');
    $last  = new DateTime($lastDate);
    $diff  = (int)$today->diff($last)->format('%r%a'); // negative = past
    if ($diff < 0) return 'Closed';
    if ($diff <= 3) return 'Closing Soon';
    return 'Open';
}

/**
 * Returns CSS class for a drive status badge.
 */
function statusBadgeClass(string $status): string {
    return match($status) {
        'Open'         => 'badge-open',
        'Closing Soon' => 'badge-soon',
        'Closed'       => 'badge-closed',
        default        => 'badge-open',
    };
}

/** MBA specializations list */
function mbaSpecializations(): array {
    return ['Finance', 'Marketing', 'HR', 'Operations', 'IT', 'General'];
}

/** MBA batch options */
function mbaBatches(): array {
    return ['2022-24', '2023-25', '2024-26', '2025-27', '2026-28'];
}
?>
