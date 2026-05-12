<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class LegacyAPIController extends Controller
{
    protected function callLegacyScript($endpoint, $method = 'GET', $data = [])
    {
        $command = ['php', base_path('legacy/aggregator.php')];
        
        if ($method === 'POST') {
            $command[] = 'POST:' . $endpoint;
            $command[] = json_encode($data);
        } else {
            $command[] = $endpoint;
            if (!empty($data)) {
                $command[] = '?' . http_build_query($data);
            }
        }
        
        $process = Process::run($command);
        
        if (!$process->successful()) {
            abort(500, 'Legacy script error: ' . $process->errorOutput());
        }
        
        return $process->output();
    }
    
    // API v3 Events
    public function events() { return $this->callLegacyScript('/api/v3/events'); }
    public function eventsLsl() { return $this->callLegacyScript('/api/v3/events/lsl'); }
    public function eventsJson() { return $this->callLegacyScript('/api/v3/events/json'); }
    public function eventsBoard() { return $this->callLegacyScript('/api/v3/events/board.png'); }
    
    // API v3 Scrup
    public function scrupGetVersion() { return $this->callLegacyScript('/api/v3/scrup/get-version'); }
    public function scrupRegisterServer(Request $request) { return $this->callLegacyScript('/api/v3/scrup/register/server', 'POST', $request->all()); }
    public function scrupRegisterScript(Request $request) { return $this->callLegacyScript('/api/v3/scrup/register/script', 'POST', $request->all()); }
    public function scrupRegisterClient(Request $request) { return $this->callLegacyScript('/api/v3/scrup/register/client', 'POST', $request->all()); }
    
    // Legacy API v2
    public function legacyEvents() { return $this->callLegacyScript('/api/v2/events'); }
    
    // EOL API v1
    public function eolEvents() { return $this->callLegacyScript('/events.lsl'); }
    
    // Fallback
    public function fallbackEvents(Request $request) {
        $format = $request->query('format', 'lsl');
        return $this->callLegacyScript('/events.php?format=' . $format);
    }
}