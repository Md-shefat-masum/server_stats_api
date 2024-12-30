<?php

header('Content-Type: application/json');

// Define functions for each requested feature
function getCPUDetails() {
    $mpstatOutput = shell_exec("mpstat -P ALL 1 1");
    if (!$mpstatOutput) {
        return ["error" => "mpstat command not available or not returning output."];
    }
    $cpuUsageLines = array_filter(explode("\n", $mpstatOutput));
    $user_info = preg_split('/\t+/', $cpuUsageLines[0]);
    $cpuDetails = [
        "cpu_info" => [
            "user" => $user_info[0],
            "date" => $user_info[1],
            "os" => $user_info[2],
            "cores" => $user_info[3],
        ],
        "cpu_states" => [],
    ];

    $cpu_states = [];
    $headings = [
        "Average:",
        "CPU",
        "%usr",
        "%nice",
        "%sys",
        "%iowait",
        "%irq",
        "%soft",
        "%steal",
        "%guest",
        "%gnice",
        "%idle"
    ];
    foreach ($cpuUsageLines as $key=>$line) {
        $cpu_info = preg_split('/\s+/', $line);
        if ($cpu_info[0] == "Average:" && $key > 1) {
            $cpu_states[] = array_combine($headings, $cpu_info);
        }
    }
    unset($cpu_states[0]);
    
    $cpuDetails["cpu_states"] = $cpu_states;

    return $cpuDetails;
}

function getMemoryAndSwapDetails() {
    $freeOutput = shell_exec('free -h');
    $lines = explode("\n", $freeOutput);
    
    $memoryLine = preg_split('/\s+/', $lines[1]);
    $swapLine = preg_split('/\s+/', $lines[2]);

    return [
        'memory' => [
            'total' => $memoryLine[1],
            'used' => $memoryLine[2],
            'free' => $memoryLine[3],
            'cached' => $memoryLine[5]
        ],
        'swap' => [
            'total' => $swapLine[1],
            'used' => $swapLine[2],
            'free' => $swapLine[3]
        ]
    ];
}

function getCPUTemperatureAndFanSpeed() {
    // Execute the sensors command
    $sensorsOutput = shell_exec('sensors');
    $lines = explode("\n", trim($sensorsOutput));

    $result = [
        'temperature' => [],
        'fan_speed' => []
    ];

    foreach ($lines as $line) {
        // Match temperature lines
        if (preg_match('/Core\s+\d+:\s+\+([\d.]+)°C/', $line, $matches)) {
            $result['temperature'][] = floatval($matches[1]);
        }

        // Match fan speed lines
        if (preg_match('/fan\d+:\s+(\d+)\s+RPM/', $line, $matches)) {
            $result['fan_speed'][] = intval($matches[1]);
        }
    }

    return $result;
}

function getNetworkIO() {
    // Read the network statistics from /proc/net/dev
    $networkData = file_get_contents('/proc/net/dev');
    $lines = explode("\n", trim($networkData));
    
    $networkStats = [];

    // Skip the first two lines, as they contain headers
    for ($i = 2; $i < count($lines); $i++) {
        $line = preg_replace('/\s+/', ' ', trim($lines[$i])); // Replace multiple spaces with a single space
        $parts = explode(' ', $line);

        $interface = rtrim($parts[0], ':'); // Remove the colon at the end of the interface name
        $receivedBytes = (int)$parts[1];   // Bytes received
        $transmittedBytes = (int)$parts[9]; // Bytes transmitted

        $networkStats[$interface] = [
            'received_bytes' => $receivedBytes,
            'transmitted_bytes' => $transmittedBytes,
        ];
    }

    return $networkStats;
}

// Function to get system uptime
function getSystemUptime() {
    // Read the uptime data from /proc/uptime
    $uptimeData = file_get_contents('/proc/uptime');
    
    if ($uptimeData === false) {
        return ['error' => 'Unable to read /proc/uptime'];
    }

    // The first value in /proc/uptime is the uptime in seconds
    $parts = explode(' ', trim($uptimeData));
    $uptimeSeconds = (float)$parts[0];

    // Convert uptime seconds into days, hours, minutes, and seconds
    $days = floor($uptimeSeconds / 86400);
    $hours = floor(($uptimeSeconds % 86400) / 3600);
    $minutes = floor(($uptimeSeconds % 3600) / 60);
    $seconds = floor($uptimeSeconds % 60);

    return [
        'uptime_seconds' => $uptimeSeconds,
        'formatted' => sprintf('%d days, %02d:%02d:%02d', $days, $hours, $minutes, $seconds)
    ];
}

function getDiskUsage($directory = '/')
{
    // Total disk space in bytes
    $totalSpace = disk_total_space($directory);

    // Free disk space in bytes
    $freeSpace = disk_free_space($directory);

    // Used disk space in bytes
    $usedSpace = $totalSpace - $freeSpace;

    // Convert bytes to GB for ease of understanding
    $totalSpaceGB = $totalSpace / (1024 * 1024 * 1024); // GB
    $freeSpaceGB = $freeSpace / (1024 * 1024 * 1024); // GB
    $usedSpaceGB = $usedSpace / (1024 * 1024 * 1024); // GB

    // Return the data as an associative array
    return [
        'total_space' => round($totalSpaceGB, 2), // Total space in GB
        'free_space' => round($freeSpaceGB, 2),   // Free space in GB
        'used_space' => round($usedSpaceGB, 2),   // Used space in GB
    ];
}

function getSystemProcesses()
{
    // Get the total number of processes using 'ps' command
    $totalProcesses = shell_exec("ps aux --no-headers | wc -l");

    // Get the currently running processes (status 'R' indicates running)
    $runningProcesses = shell_exec("ps aux --no-headers | grep ' R ' | wc -l");

    // Return as associative array
    return [
        'total_processes' => (int) trim($totalProcesses),
        'running_processes' => (int) trim($runningProcesses),
    ];
}

function getRunningProcesses()
{
    // Get the processes consuming CPU, sorted by CPU usage
    $processes = shell_exec("ps aux --sort=-%cpu --no-headers | awk '{if ($3 > 0) print $11, $3}'");

    // Split the output into an array (one process per line)
    $processList = explode("\n", trim($processes));

    // Remove any empty array elements
    $processList = array_filter($processList);

    // Format as associative array with process name and CPU usage
    $cpuProcesses = [];
    foreach ($processList as $process) {
        list($name, $cpu) = preg_split('/\s+/', $process);
        $cpuProcesses[] = [
            'process_name' => $name,
            'cpu_usage' => $cpu . '%'
        ];
    }

    return $cpuProcesses;
}

function getSystemStats()
{
//     $demo = "top - 22:41:19 up  2:13,  1 user,  load average: 0.19, 0.40, 0.43
// Tasks: 320 total,   1 running, 319 sleeping,   0 stopped,   0 zombie
// %Cpu(s):  0.3 us,  0.3 sy,  0.0 ni, 99.3 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
// MiB Mem :  19787.1 total,  12612.0 free,   3109.4 used,   4065.8 buff/cache
// MiB Swap:  11444.0 total,  11444.0 free,      0.0 used.  15585.3 avail Mem ";


    // Execute the 'top' command and get the output
    $output = shell_exec("top -bn1");

    // Initialize an empty array to hold the data
    $stats = [];

    preg_match('/top\s*-\s*(\d{2}:\d{2}:\d{2})\s+up\s+(\d{1,2}:\d{2}),\s+(\d+)\s+user/', $output, $matches);
    if ($matches) {
        $systemInfo = [
            'time' => $matches[1],
            'uptime' => $matches[2],
            'user_count' => $matches[3]
        ];
        $stats["system_info"] = $systemInfo;
    }

    // Extract the load averages
    preg_match('/load average:\s+([\d\.]+),\s+([\d\.]+),\s+([\d\.]+)/', $output, $matches);
    if (isset($matches[0])) {
        // $loadAverages = explode(", ", $matches[1]);
        $loadAverages = $matches;
        $stats['load_average'] = [
            'string' => trim($loadAverages[0]),
            '1_min' => trim($loadAverages[1]),
            '5_min' => trim($loadAverages[2]),
            '15_min' => trim($loadAverages[3]),
        ];
    }

    // Extract CPU usage
    preg_match('/%Cpu\(s\):\s+([0-9\.]+) us,\s+([0-9\.]+) sy,\s+([0-9\.]+) ni,\s+([0-9\.]+) id/', $output, $matches);
    if (isset($matches[1])) {
        $stats['cpu_usage'] = [
            'user' => $matches[1] . '%',
            'system' => $matches[2] . '%',
            'nice' => $matches[3] . '%',
            'idle' => $matches[4] . '%',
        ];
    }

    // Extract memory usage
    preg_match('/MiB Mem :\s+([0-9\.]+) total,\s+([0-9\.]+) free,\s+([0-9\.]+) used,\s+([0-9\.]+) buff\/cache/', $output, $matches);
    if (isset($matches[1])) {
        $stats['memory_usage'] = [
            'total' => $matches[1] . ' MiB',
            'free' => $matches[2] . ' MiB',
            'used' => $matches[3] . ' MiB',
            'buff_cache' => $matches[4] . ' MiB',
        ];
    }

    // Extract swap usage
    preg_match('/MiB Swap:\s+([0-9\.]+) total,\s+([0-9\.]+) free,\s+([0-9\.]+) used/', $output, $matches);
    if (isset($matches[1])) {
        $stats['swap_usage'] = [
            'total' => $matches[1] . ' MiB',
            'free' => $matches[2] . ' MiB',
            'used' => $matches[3] . ' MiB',
        ];
    }

    // Extract total tasks and states
    preg_match('/Tasks:\s+([0-9]+) total,\s+([0-9]+) running,\s+([0-9]+) sleeping/', $output, $matches);
    if (isset($matches[1])) {
        $stats['tasks'] = [
            'total' => $matches[1],
            'running' => $matches[2],
            'sleeping' => $matches[3],
        ];
    }

    return $stats;
}

function getBusyPortsUsage() {
    // Execute the command to get active listening ports (TCP and UDP)
    $command = "ss -tuln";  // For TCP and UDP listening ports
    $output = shell_exec($command);

    // Initialize an array to store port usage
    $portUsage = [];

    if ($output) {
        // Split the output into lines
        $lines = explode("\n", $output);
        
        // Iterate through each line and extract the necessary information
        foreach ($lines as $line) {
            // Match lines that contain listening ports and their usage
            if (preg_match('/\s*(\S+)\s+([0-9\.]+):(\d+)\s/', $line, $matches)) {
                $protocol = $matches[1];  // Protocol (TCP/UDP)
                $ip = $matches[2];        // IP address (e.g., 0.0.0.0 or specific IP)
                $port = $matches[3];      // Port number
                
                // Add port and its usage (number of connections)
                if (!isset($portUsage[$port])) {
                    $portUsage[$port] = [
                        'protocol' => $protocol,
                        'ip' => $ip,
                        'connections' => 1
                    ];
                } else {
                    $portUsage[$port]['connections']++;
                }
            }
        }
    }

    return $portUsage;
}
function getIPConfigs() {
    // Command to get network configuration details
    $command = 'ip addr'; // For Linux
    // $command = 'ifconfig'; // Alternative command for older systems

    // Execute the command
    $output = shell_exec($command);

    if ($output) {
        // Parse output to extract IP configurations
        $ipConfigs = [];
        preg_match_all('/inet\s+([\d\.]+)\/\d+\s+.*?(?=brd|scope)/', $output, $matches);

        if (isset($matches[1])) {
            $ipConfigs['IPv4'] = $matches[1];
        }

        preg_match_all('/inet6\s+([a-f0-9:]+)\/\d+/', $output, $matches6);

        if (isset($matches6[1])) {
            $ipConfigs['IPv6'] = $matches6[1];
        }

        return $ipConfigs;
    }

    return null; // No output or error
}


$response = [
    'ip_config' => getIPConfigs(),
    'cpu_details' => getCPUDetails(),
    'memory_and_swap' => getMemoryAndSwapDetails(),
    'fan' => getCPUTemperatureAndFanSpeed(),
    'network_io' => getNetworkIO(),
    'uptime' => getSystemUptime(),
    'disk' => getDiskUsage(),
    'processes' => getSystemProcesses(),
    'running_processes' => getRunningProcesses(),
    'system_usage' => getSystemStats(),
    'busy_ports' => getBusyPortsUsage(),
];

// Return the JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

