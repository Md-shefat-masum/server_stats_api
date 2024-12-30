<?php

header('Content-Type: application/json');

// Define functions for each requested feature
function getRunningProcesses() {
    $processes = shell_exec('ps aux');
    return explode("\n", trim($processes));
}

function countProcesses() {
    return count(getRunningProcesses()) - 1; // Subtracting 1 for the header line
}

function getCPUUsagePercent() {
    $cpuUsage = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'");
    return round((float) $cpuUsage, 2);
}

function getRAMUsagePercent() {
    $freeOutput = shell_exec('free | grep Mem');
    $data = preg_split('/\s+/', $freeOutput);
    $used = $data[2];
    $total = $data[1];
    return round(($used / $total) * 100, 2);
}

function getCoresInfo() {
    $totalCores = (int) shell_exec('nproc');
    $usedCores = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'");
    $usedCores = round(($totalCores * ((float) $usedCores / 100)), 2);
    return ['total_cores' => $totalCores, 'used_cores' => $usedCores];
}

function getNetworkUsage() {
    $initialData = getNetworkData();
    sleep(1); // Pause for 1 second
    $finalData = getNetworkData();

    $incoming = $finalData['rx'] - $initialData['rx'];
    $outgoing = $finalData['tx'] - $initialData['tx'];

    return ['incoming' => $incoming, 'outgoing' => $outgoing];
}

function getNetworkData() {
    $networkData = shell_exec("cat /proc/net/dev | grep -E 'eth0|wlan0'");
    $lines = explode("\n", trim($networkData));
    $rx = $tx = 0;

    foreach ($lines as $line) {
        $data = preg_split('/\s+/', trim($line));
        $rx += (int) $data[1]; // Bytes received
        $tx += (int) $data[9]; // Bytes transmitted
    }

    return ['rx' => $rx, 'tx' => $tx];
}

// Define the response structure
$response = [
    'running_processes' => getRunningProcesses(),
    'process_count' => countProcesses(),
    'cpu_usage_percent' => getCPUUsagePercent(),
    'ram_usage_percent' => getRAMUsagePercent(),
    'core_info' => getCoresInfo(),
    'network_usage' => getNetworkUsage()
];

// Return the JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

