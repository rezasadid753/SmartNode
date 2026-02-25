<?php
// CONFIGURATION
$password_protect = "1670";
$file_path = 'status.txt';
$timezone = "Asia/Tehran"; 
date_default_timezone_set($timezone);

// DEFAULT DATA
$defaults = [
    'lastreached' => '00:00:00',
    'plug1' => 'off', 'plug2' => 'off',
    'plug1_timer' => 'none', 'plug2_timer' => 'none',
    'plug1_timer_end' => '0', 'plug2_timer_end' => '0',
    'plug1_schedule' => 'none', 'plug2_schedule' => 'none',
    'plug1_show' => 'no', 'plug2_show' => 'no'
];

// --- BACKEND ---
function readData() {
    global $file_path, $defaults;
    if (!file_exists($file_path)) { writeData($defaults); return $defaults; }
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];
    foreach ($lines as $line) {
        $parts = explode('=', $line);
        if (count($parts) == 2) $data[trim($parts[0])] = trim($parts[1]);
    }
    return array_merge($defaults, $data);
}

function writeData($data) {
    global $file_path;
    $content = "";
    foreach ($data as $key => $val) $content .= "$key = $val\n";
    file_put_contents($file_path, $content, LOCK_EX);
}

function checkOffline($data) {
    $now = time();
    $last_ts = strtotime(date("Y-m-d") . " " . $data['lastreached']);
    if ($last_ts > $now) $last_ts = strtotime("-1 day " . $data['lastreached']);
    return ($now - $last_ts) > 5; // Changed from 120 to 5 seconds
}

// --- API ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action'])) {
        if ($input['action'] === 'login') {
            echo json_encode(['status' => ($input['password'] === $password_protect ? 'success' : 'error')]);
        } 
        elseif ($input['action'] === 'get_status') {
            $data = readData();
            $now = time();
            $updated = false;
            foreach(['plug1', 'plug2'] as $p) {
                if($data[$p.'_timer'] !== 'none' && $data[$p.'_timer_end'] > 0) {
                    if($now >= intval($data[$p.'_timer_end'])) {
                        $data[$p.'_timer'] = 'none';
                        $data[$p.'_timer_end'] = '0';
                        $data[$p] = 'off'; 
                        $updated = true;
                    }
                }
            }
            if($updated) writeData($data);
            echo json_encode(['status'=>'success', 'data'=>$data, 'is_offline'=>checkOffline($data), 'raw'=>file_get_contents($file_path), 'server_time'=>$now]);
        }
        elseif ($input['action'] === 'update') {
            $data = readData();
            foreach ($input['updates'] as $key => $val) {
                $data[$key] = $val;
                if(strpos($key, '_timer') !== false && $val !== 'none') {
                    $mins = intval($val);
                    $data[$key.'_end'] = time() + ($mins * 60);
                }
                if(strpos($key, '_timer') !== false && $val === 'none') {
                    $data[$key.'_end'] = '0';
                }
            }
            writeData($data);
            echo json_encode(['status' => 'success']);
        }
        elseif ($input['action'] === 'device_heartbeat') {
            $data = readData();
            $data['lastreached'] = date('H:i:s');
            writeData($data);
            echo json_encode($data); 
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Smart Node</title>
    
    <!-- FAVICON (UPDATED SVG) -->
    <link rel="icon" href="data:image/svg+xml;charset=utf-8,%3Csvg width='620' height='620' viewBox='0 0 620 620' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 220C0 98.4974 98.4974 0 220 0H400C521.503 0 620 98.4974 620 220V400C620 521.503 521.503 620 400 620H220C98.4974 620 0 521.503 0 400V220Z' fill='%232563EB'/%3E%3Cpath d='M232.388 355.7C219.032 343.281 208.844 327.806 202.687 310.584C196.53 293.362 194.585 274.901 197.017 256.762C199.421 238.37 206.221 220.839 216.831 205.672C227.442 190.505 241.548 178.154 257.941 169.678C274.333 161.201 292.523 156.852 310.951 157.004C329.379 157.155 347.496 161.802 363.749 170.547C380.002 179.291 393.906 191.872 404.27 207.212C414.633 222.551 421.147 240.192 423.253 258.621C425.359 277.05 422.995 295.718 416.362 313.026C409.729 330.333 399.026 345.764 385.17 357.994C376.248 365.716 369.321 375.502 364.984 386.512H322.71V294.93C330.092 292.303 336.488 287.444 341.022 281.018C345.555 274.592 348.006 266.913 348.039 259.032C348.039 255.651 346.704 252.408 344.329 250.017C341.954 247.627 338.733 246.284 335.374 246.284C332.016 246.284 328.794 247.627 326.419 250.017C324.044 252.408 322.71 255.651 322.71 259.032C322.71 262.413 321.376 265.655 319.001 268.046C316.626 270.436 313.405 271.78 310.046 271.78C306.687 271.78 303.466 270.436 301.091 268.046C298.716 265.655 297.382 262.413 297.382 259.032C297.382 255.651 296.047 252.408 293.672 250.017C291.297 247.627 288.076 246.284 284.717 246.284C281.358 246.284 278.137 247.627 275.762 250.017C273.387 252.408 272.053 255.651 272.053 259.032C272.085 266.913 274.536 274.592 279.07 281.018C283.604 287.444 289.999 292.303 297.382 294.93V386.512H253.854C249.013 374.791 241.687 364.275 232.388 355.7ZM259.389 412.008V415.96C259.402 428.431 264.33 440.388 273.091 449.207C281.852 458.026 293.73 462.987 306.12 463H313.972C326.362 462.987 338.24 458.026 347.001 449.207C355.762 440.388 360.69 428.431 360.703 415.96V412.008H259.389Z' fill='white'/%3E%3C/svg%3E">
    
    <link rel="apple-touch-icon" href="data:image/svg+xml;charset=utf-8,%3Csvg width='620' height='620' viewBox='0 0 620 620' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 220C0 98.4974 98.4974 0 220 0H400C521.503 0 620 98.4974 620 220V400C620 521.503 521.503 620 400 620H220C98.4974 620 0 521.503 0 400V220Z' fill='%232563EB'/%3E%3Cpath d='M232.388 355.7C219.032 343.281 208.844 327.806 202.687 310.584C196.53 293.362 194.585 274.901 197.017 256.762C199.421 238.37 206.221 220.839 216.831 205.672C227.442 190.505 241.548 178.154 257.941 169.678C274.333 161.201 292.523 156.852 310.951 157.004C329.379 157.155 347.496 161.802 363.749 170.547C380.002 179.291 393.906 191.872 404.27 207.212C414.633 222.551 421.147 240.192 423.253 258.621C425.359 277.05 422.995 295.718 416.362 313.026C409.729 330.333 399.026 345.764 385.17 357.994C376.248 365.716 369.321 375.502 364.984 386.512H322.71V294.93C330.092 292.303 336.488 287.444 341.022 281.018C345.555 274.592 348.006 266.913 348.039 259.032C348.039 255.651 346.704 252.408 344.329 250.017C341.954 247.627 338.733 246.284 335.374 246.284C332.016 246.284 328.794 247.627 326.419 250.017C324.044 252.408 322.71 255.651 322.71 259.032C322.71 262.413 321.376 265.655 319.001 268.046C316.626 270.436 313.405 271.78 310.046 271.78C306.687 271.78 303.466 270.436 301.091 268.046C298.716 265.655 297.382 262.413 297.382 259.032C297.382 255.651 296.047 252.408 293.672 250.017C291.297 247.627 288.076 246.284 284.717 246.284C281.358 246.284 278.137 247.627 275.762 250.017C273.387 252.408 272.053 255.651 272.053 259.032C272.085 266.913 274.536 274.592 279.07 281.018C283.604 287.444 289.999 292.303 297.382 294.93V386.512H253.854C249.013 374.791 241.687 364.275 232.388 355.7ZM259.389 412.008V415.96C259.402 428.431 264.33 440.388 273.091 449.207C281.852 458.026 293.73 462.987 306.12 463H313.972C326.362 462.987 338.24 458.026 347.001 449.207C355.762 440.388 360.69 428.431 360.703 415.96V412.008H259.389Z' fill='white'/%3E%3C/svg%3E">

    <script src="tailwindcsscdn-3.4.17.js"></script>
    <link rel="stylesheet" href="fontawesome-6.4.0/fontawesome-6.4.0.css">
    <script src="jquery-4.0.0.min.js"></script>
    <style>
        body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; overscroll-behavior: none; }
        
        /* Modern App Card */
        .app-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border-radius: 24px;
        }

        /* Power Button */
        .power-btn {
            background: #e2e8f0;
            box-shadow: 5px 5px 10px #cbd5e1, -5px -5px 10px #ffffff;
            color: #94a3b8;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 4px solid #f1f5f9;
        }
        .power-btn:active { transform: scale(0.95); }
        .power-btn.on {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.5);
            color: white; border-color: #eff6ff;
        }

        /* Action Buttons */
        .action-btn {
            background: #ffffff; border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border-radius: 16px; transition: all 0.2s ease;
        }
        .action-btn:active { transform: scale(0.96); background: #f8fafc; }
        .action-btn.active-mode {
            background: #f0fdf4; border-color: #86efac; color: #15803d;
            box-shadow: 0 0 0 2px rgba(134, 239, 172, 0.3);
        }
        .action-btn.timer-running {
            background: #eff6ff; border-color: #93c5fd; color: #1d4ed8;
        }

        /* PIN Inputs */
        .pin-input {
            width: 50px; height: 60px; background: #e2e8f0;
            box-shadow: inset 2px 2px 5px #cbd5e1, inset -2px -2px 5px #ffffff;
            border-radius: 12px; border: none; outline: none;
            font-size: 24px; font-weight: 800; text-align: center;
        }
        .pin-input:focus { background: white; box-shadow: 0 0 0 2px #3b82f6; }
        .pin-input.error { animation: shake 0.4s ease-in-out; box-shadow: 0 0 0 2px #ef4444; color: #ef4444; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        /* Terminal Animation */
        .loading-dots div { animation: blinkSeq 1.4s infinite both; }
        .loading-dots div:nth-child(2) { animation-delay: 0.2s; }
        .loading-dots div:nth-child(3) { animation-delay: 0.4s; }
        @keyframes blinkSeq { 0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); } 40% { opacity: 1; transform: scale(1.1); } }

        /* Expanded Terminal */
        #terminal-panel { transition: height 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        #terminal-panel.expanded { height: 75vh; border-top: 1px solid rgba(255,255,255,0.1); }
        
        /* INCREASED DEFAULT HEIGHT */
        #terminal-panel.collapsed { height: 10rem; } 
        
        #app-blur-layer { transition: all 0.4s ease; }
        #app-blur-layer.blur-active { filter: blur(5px) grayscale(0.2); transform: scale(0.98); opacity: 0.6; }

        .blink-mode { animation: flash-btn 1s infinite; background: #f59e0b !important; color: white !important; }
        @keyframes flash-btn { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .offline-overlay { filter: grayscale(1); opacity: 0.6; pointer-events: none; }

        /* CUSTOM NARROW SCROLLBAR FOR TERMINAL */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }
    </style>
</head>
<body class="h-screen w-full overflow-hidden select-none bg-[#f1f5f9]">

    <!-- LOGIN SCREEN -->
    <div id="login-screen" class="fixed inset-0 z-50 bg-[#f1f5f9] flex flex-col items-center justify-center p-6">
        <div class="mb-10 text-center">
            
            <!-- NEW SVG ICON HERE -->
            <div class="w-24 h-24 mx-auto mb-6 transition-transform hover:scale-105 duration-300 drop-shadow-xl">
                <svg viewBox="0 0 620 620" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <path d="M0 220C0 98.4974 98.4974 0 220 0H400C521.503 0 620 98.4974 620 220V400C620 521.503 521.503 620 400 620H220C98.4974 620 0 521.503 0 400V220Z" fill="#2563EB"/>
                    <path d="M232.388 355.7C219.032 343.281 208.844 327.806 202.687 310.584C196.53 293.362 194.585 274.901 197.017 256.762C199.421 238.37 206.221 220.839 216.831 205.672C227.442 190.505 241.548 178.154 257.941 169.678C274.333 161.201 292.523 156.852 310.951 157.004C329.379 157.155 347.496 161.802 363.749 170.547C380.002 179.291 393.906 191.872 404.27 207.212C414.633 222.551 421.147 240.192 423.253 258.621C425.359 277.05 422.995 295.718 416.362 313.026C409.729 330.333 399.026 345.764 385.17 357.994C376.248 365.716 369.321 375.502 364.984 386.512H322.71V294.93C330.092 292.303 336.488 287.444 341.022 281.018C345.555 274.592 348.006 266.913 348.039 259.032C348.039 255.651 346.704 252.408 344.329 250.017C341.954 247.627 338.733 246.284 335.374 246.284C332.016 246.284 328.794 247.627 326.419 250.017C324.044 252.408 322.71 255.651 322.71 259.032C322.71 262.413 321.376 265.655 319.001 268.046C316.626 270.436 313.405 271.78 310.046 271.78C306.687 271.78 303.466 270.436 301.091 268.046C298.716 265.655 297.382 262.413 297.382 259.032C297.382 255.651 296.047 252.408 293.672 250.017C291.297 247.627 288.076 246.284 284.717 246.284C281.358 246.284 278.137 247.627 275.762 250.017C273.387 252.408 272.053 255.651 272.053 259.032C272.085 266.913 274.536 274.592 279.07 281.018C283.604 287.444 289.999 292.303 297.382 294.93V386.512H253.854C249.013 374.791 241.687 364.275 232.388 355.7ZM259.389 412.008V415.96C259.402 428.431 264.33 440.388 273.091 449.207C281.852 458.026 293.73 462.987 306.12 463H313.972C326.362 462.987 338.24 458.026 347.001 449.207C355.762 440.388 360.69 428.431 360.703 415.96V412.008H259.389Z" fill="white"/>
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-slate-700">Access Control</h1>
            <p class="text-slate-400 text-sm">Enter PIN to connect</p>
        </div>
        <div class="flex gap-3 mb-8">
            <input type="tel" maxlength="1" class="pin-input" data-index="0">
            <input type="tel" maxlength="1" class="pin-input" data-index="1">
            <input type="tel" maxlength="1" class="pin-input" data-index="2">
            <input type="tel" maxlength="1" class="pin-input" data-index="3">
        </div>
        <p id="login-msg" class="h-6 text-red-500 font-bold text-sm"></p>
    </div>

    <!-- MAIN APP CONTAINER -->
    <div id="app" class="hidden w-full h-full bg-slate-50 relative overflow-hidden md:max-w-lg md:mx-auto md:rounded-3xl md:shadow-2xl md:h-[95vh] md:my-[2.5vh] md:border md:border-white">
        
        <!-- BLUR WRAPPER -->
        <div id="app-blur-layer" class="w-full flex flex-col">
            <!-- Header -->
            <header class="p-6 pb-2 flex justify-between items-end z-10">
                <div>
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Controller</h2>
                    <div class="flex items-center gap-2">
                        <div id="conn-dot" class="w-3 h-3 rounded-full bg-slate-300"></div>
                        <h1 class="text-2xl font-black text-slate-700 leading-none">Smart<span class="text-blue-600">Node</span></h1>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Last Sync</div>
                    <div id="last-seen" class="font-mono font-bold text-slate-600 text-sm">--:--:--</div>
                </div>
            </header>

            <!-- Scrollable Content -->
            <!-- Reduced padding px-2 to let cards fill width more -->
            <div class="flex-1 overflow-y-auto p-2 pb-44 space-y-3">
                <?php foreach(['plug1'=>'Plug 01', 'plug2'=>'Plug 02'] as $p => $label): ?>
                <div class="app-card p-5 relative overflow-hidden transition-all w-full" data-id="<?php echo $p; ?>">
                    <!-- Top Row -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?php echo $label; ?></span>
                            <span class="status-text text-sm font-bold text-slate-300 mt-1">OFF</span>
                        </div>
                        <button class="power-btn w-16 h-16 rounded-2xl flex items-center justify-center text-2xl" onclick="toggle('<?php echo $p; ?>')">
                            <i class="fa-solid fa-power-off"></i>
                        </button>
                    </div>

                    <!-- Actions Grid -->
                    <div class="grid grid-cols-3 gap-3">
                        <button class="action-btn py-3 flex flex-col items-center justify-center gap-1 show-btn" onclick="triggerShow('<?php echo $p; ?>')">
                            <i class="fa-regular fa-eye text-lg mb-1"></i><span class="text-[10px] font-bold uppercase">Find</span>
                        </button>

                        <button class="action-btn py-3 flex flex-col items-center justify-center gap-1 timer-btn" onclick="handleTimer('<?php echo $p; ?>')">
                            <i class="fa-solid fa-hourglass-start text-lg mb-1 icon-main"></i>
                            <span class="text-[10px] font-bold uppercase val-text">Timer</span>
                        </button>

                        <button class="action-btn py-3 flex flex-col items-center justify-center gap-1 sched-btn" onclick="handleSched('<?php echo $p; ?>')">
                            <i class="fa-regular fa-calendar text-lg mb-1 icon-main"></i>
                            <span class="text-[10px] font-bold uppercase val-text">Sched</span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TERMINAL -->
        <div id="terminal-panel" class="absolute bottom-0 w-full bg-slate-900 text-slate-400 shadow-[0_-10px_40px_rgba(0,0,0,0.2)] z-30 collapsed flex flex-col md:rounded-b-3xl">
            <!-- Header -->
            <div class="flex justify-between items-center px-5 py-4 cursor-pointer hover:bg-white/5 transition" onclick="toggleTerminal()">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-blue-400"><i class="fa-solid fa-terminal text-xs"></i></div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">System Log</div>
                        <div class="flex gap-1.5 loading-dots mt-1">
                            <div class="w-1 h-1 rounded-full bg-blue-500"></div>
                            <div class="w-1 h-1 rounded-full bg-blue-500"></div>
                            <div class="w-1 h-1 rounded-full bg-blue-500"></div>
                        </div>
                    </div>
                </div>
                <!-- ACCORDION ICON: Points UP when collapsed (to expand), DOWN when extended -->
                <button class="text-slate-500 hover:text-white transition">
                    <i id="term-icon" class="fa-solid fa-chevron-up text-lg"></i>
                </button>
            </div>
            
            <!-- Content with Custom Scrollbar -->
            <div class="flex-1 overflow-auto px-5 pb-5 custom-scrollbar">
                <pre id="term-out" class="font-mono text-[11px] leading-relaxed opacity-80 whitespace-pre-wrap text-green-400"></pre>
            </div>
        </div>

        <!-- MODALS -->
        <div id="timer-modal" class="absolute inset-0 z-40 bg-white/90 backdrop-blur-md flex flex-col items-center justify-center p-8 hidden">
            <h3 class="text-xl font-bold text-slate-800 mb-6">Set Timer</h3>
            <div class="grid grid-cols-2 gap-4 w-full mb-6">
                <?php foreach([15,30,60,90] as $t) echo "<button onclick='setTimer($t)' class='bg-white border border-slate-200 shadow-sm py-4 rounded-2xl text-lg font-bold text-slate-600 active:scale-95 transition'>{$t}m</button>"; ?>
            </div>
            <button onclick="$('#timer-modal').addClass('hidden')" class="text-slate-400 font-bold text-sm tracking-widest uppercase">Cancel</button>
        </div>

        <div id="sched-modal" class="absolute inset-0 z-40 bg-white/90 backdrop-blur-md flex flex-col items-center justify-center p-8 hidden">
            <h3 class="text-xl font-bold text-slate-800 mb-6">Set Schedule</h3>
            <div class="flex items-center gap-2 mb-8 bg-white p-2 rounded-2xl shadow-sm border border-slate-200">
                <select id="s-start" class="bg-transparent text-xl font-bold text-slate-700 outline-none p-2"></select>
                <span class="text-slate-300 font-bold">to</span>
                <select id="s-end" class="bg-transparent text-xl font-bold text-slate-700 outline-none p-2"></select>
            </div>
            <button onclick="setSched()" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold shadow-lg shadow-blue-200 active:scale-95 transition mb-4">Confirm</button>
            <button onclick="$('#sched-modal').addClass('hidden')" class="text-slate-400 font-bold text-sm tracking-widest uppercase">Cancel</button>
        </div>

    </div>

    <script>
        let localData = {}, activeId = null, serverOffset = 0;
        let isTermExpanded = false;

        // --- PIN LOGIC ---
        $('.pin-input').on('keyup', function(e) {
            let idx = $(this).data('index');
            let val = $(this).val();
            if (val.length === 1 && idx < 3) $(`.pin-input[data-index="${idx+1}"]`).focus();
            if (e.key === 'Backspace' && idx > 0 && val.length === 0) $(`.pin-input[data-index="${idx-1}"]`).focus();
            let pin = ''; $('.pin-input').each((i, el) => pin += $(el).val());
            if (pin.length === 4) login(pin);
        });

        function login(pin) {
            $.ajax({
                url: 'index.php', type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ action: 'login', password: pin }),
                success: (res) => {
                    if (res.status === 'success') {
                        $('#login-screen').fadeOut(300);
                        $('#app').removeClass('hidden').addClass('flex');
                        init();
                    } else {
                        $('.pin-input').addClass('error').val('').first().focus();
                        $('#login-msg').text('INCORRECT PIN');
                        setTimeout(() => $('.pin-input').removeClass('error'), 500);
                    }
                }
            });
        }

        function init() {
            for(let i=1; i<=24; i++) { let v = i.toString().padStart(2,'0'); $('#s-start, #s-end').append(`<option value="${v}">${v}:00</option>`); }
            setInterval(poll, 1000); poll();
        }

        function poll() {
            $.ajax({
                url: 'index.php', type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ action: 'get_status' }),
                success: (res) => {
                    if (res.status === 'success') {
                        localData = res.data;
                        serverOffset = res.server_time - Math.floor(Date.now() / 1000);
                        render(res);
                    }
                }
            });
        }

        function render(res) {
            const d = res.data;
            $('#term-out').text(res.raw);
            $('#last-seen').text(d.lastreached);

            if(res.is_offline) {
                $('#conn-dot').removeClass('bg-green-500 shadow-[0_0_10px_lime]').addClass('bg-red-500');
                $('.app-card').addClass('offline-overlay');
            } else {
                $('#conn-dot').addClass('bg-green-500 shadow-[0_0_10px_lime]').removeClass('bg-red-500');
                $('.app-card').removeClass('offline-overlay');
            }

            ['plug1', 'plug2'].forEach(id => {
                const card = $(`[data-id="${id}"]`);
                
                // Power
                const pBtn = card.find('.power-btn');
                const sTxt = card.find('.status-text');
                if (d[id] === 'on') {
                    pBtn.addClass('on');
                    sTxt.text('ACTIVE').removeClass('text-slate-300').addClass('text-blue-500');
                } else {
                    pBtn.removeClass('on');
                    sTxt.text('OFF').removeClass('text-blue-500').addClass('text-slate-300');
                }

                // Show
                const sBtn = card.find('.show-btn');
                if (d[id+'_show'] === 'yes') sBtn.addClass('blink-mode');
                else sBtn.removeClass('blink-mode');

                // Timer
                const tBtn = card.find('.timer-btn');
                const tEnd = parseInt(d[id+'_timer_end']);
                const tVal = d[id+'_timer'];
                if (tVal !== 'none' && tEnd > 0) {
                    const now = Math.floor(Date.now() / 1000) + serverOffset;
                    let diff = tEnd - now;
                    if(diff > 0) {
                        let m = Math.floor(diff / 60);
                        let s = diff % 60;
                        tBtn.find('.val-text').text(`${m}:${s.toString().padStart(2,'0')}`);
                        tBtn.addClass('timer-running');
                        tBtn.find('.icon-main').addClass('hidden'); 
                    } else { tBtn.find('.val-text').text('0:00'); }
                } else {
                    tBtn.find('.val-text').text('Timer');
                    tBtn.removeClass('timer-running');
                    tBtn.find('.icon-main').removeClass('hidden');
                }

                // Sched
                const scBtn = card.find('.sched-btn');
                const scVal = d[id+'_schedule'];
                if(scVal !== 'none') {
                    scBtn.addClass('active-mode');
                    scBtn.find('.val-text').text(scVal);
                    scBtn.find('.icon-main').addClass('hidden'); 
                } else {
                    scBtn.removeClass('active-mode');
                    scBtn.find('.val-text').text('Sched');
                    scBtn.find('.icon-main').removeClass('hidden');
                }
            });
        }

        // --- UI ACTIONS ---
        function toggleTerminal() {
            isTermExpanded = !isTermExpanded;
            const panel = $('#terminal-panel');
            const blur = $('#app-blur-layer');
            const icon = $('#term-icon');

            if (isTermExpanded) {
                panel.removeClass('collapsed').addClass('expanded');
                blur.addClass('blur-active');
                // Extended state: Icon points DOWN to indicate collapsing
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            } else {
                panel.removeClass('expanded').addClass('collapsed');
                blur.removeClass('blur-active');
                // Collapsed state: Icon points UP to indicate expanding
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }
        }

        // --- LOGIC ACTIONS ---
        function update(obj) { $.ajax({ url: 'index.php', type: 'POST', contentType: 'application/json', data: JSON.stringify({ action: 'update', updates: obj }), success: poll }); }
        function toggle(id) { update({ [id]: localData[id] === 'on' ? 'off' : 'on' }); }
        function triggerShow(id) { update({ [id+'_show']: 'yes' }); setTimeout(() => update({ [id+'_show']: 'no' }), 3000); }
        function handleTimer(id) { localData[id+'_timer'] !== 'none' ? update({ [id+'_timer']: 'none' }) : (activeId = id, $('#timer-modal').removeClass('hidden')); }
        function setTimer(m) { update({ [activeId+'_timer']: m }); $('#timer-modal').addClass('hidden'); }
        function handleSched(id) { localData[id+'_schedule'] !== 'none' ? update({ [id+'_schedule']: 'none' }) : (activeId = id, $('#sched-modal').removeClass('hidden')); }
        function setSched() { update({ [activeId+'_schedule']: $('#s-start').val() + '-' + $('#s-end').val() }); $('#sched-modal').addClass('hidden'); }

    </script>
</body>
</html>
