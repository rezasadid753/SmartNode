<?php
// cron.php - AUTOMATED TASK RUNNER
// Runs every minute via system crontab to handle Timers and Schedules.

// 1. CONFIGURATION
$file_path = '/var/www/dev/smartnode/status.txt'; // Absolute path is safer for Cron
$timezone = "Asia/Tehran";
date_default_timezone_set($timezone);

// Prevent running if file is missing
if (!file_exists($file_path)) {
    error_log("SmartNode Cron Error: status.txt not found at $file_path");
    exit("File not found");
}

// 2. READ DATA
// Using file() to read lines into an array
$lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$data = [];
foreach ($lines as $line) {
    $parts = explode('=', $line);
    if (count($parts) == 2) {
        $data[trim($parts[0])] = trim($parts[1]);
    }
}

$updated = false;
$now = time();
$current_hour = intval(date('H'));

// 3. CHECK OFFLINE STATUS
// If lastreached is older than 5 seconds, we consider it offline.
// We DO NOT turn off plugs (safety), but we stop the "Show/Blink" effect to save power/sanity.
$last_str = isset($data['lastreached']) ? $data['lastreached'] : '00:00:00';
$last_ts = strtotime(date("Y-m-d") . " " . $last_str);
if ($last_ts > $now) $last_ts = strtotime("-1 day " . $last_str); // Handle midnight wrap

if (($now - $last_ts) > 60) { // If offline for > 1 minute
    if ($data['plug1_show'] !== 'no') { $data['plug1_show'] = 'no'; $updated = true; }
    if ($data['plug2_show'] !== 'no') { $data['plug2_show'] = 'no'; $updated = true; }
}

// 4. TIMER LOGIC (Precise Timestamp)
foreach(['plug1', 'plug2'] as $p) {
    // Check if timer is active ('none' check) AND has a valid end timestamp
    if ($data[$p.'_timer'] !== 'none' && isset($data[$p.'_timer_end']) && $data[$p.'_timer_end'] > 0) {
        $endTime = intval($data[$p.'_timer_end']);
        
        // If current time passed the end time
        if ($now >= $endTime) {
            $data[$p] = 'off';           // Turn Relay OFF
            $data[$p.'_timer'] = 'none'; // Reset Timer Display
            $data[$p.'_timer_end'] = '0';// Reset Timestamp
            $updated = true;
            // echo "$p timer finished. ";
        }
    }
}

// 5. SCHEDULE LOGIC (Hour Based)
foreach(['plug1', 'plug2'] as $p) {
    if ($data[$p.'_schedule'] !== 'none') {
        // Format expected: "06-20" (Start-End)
        $parts = explode('-', $data[$p.'_schedule']);
        if (count($parts) === 2) {
            $start = intval($parts[0]);
            $end = intval($parts[1]);

            // If it is currently the Start Hour -> Turn ON
            if ($current_hour == $start && $data[$p] !== 'on') {
                $data[$p] = 'on'; 
                $updated = true;
                // echo "$p schedule start. ";
            }
            // If it is currently the End Hour -> Turn OFF
            elseif ($current_hour == $end && $data[$p] !== 'off') {
                $data[$p] = 'off'; 
                $updated = true;
                // echo "$p schedule end. ";
            }
        }
    }
}

// 6. SAVE CHANGES
if ($updated) {
    $content = "";
    foreach ($data as $key => $val) {
        $content .= "$key = $val\n";
    }
    // LOCK_EX prevents race conditions between Cron and User
    file_put_contents($file_path, $content, LOCK_EX);
    echo "Status updated successfully.";
} else {
    echo "No changes required.";
}
?>