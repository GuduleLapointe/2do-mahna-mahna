# Legacy API Routes - Implementation Guide

Implement in Laravel the legacy routes currently defined in `legacy/src/bundle/standalone/index.php`.

## Complete Route List for Laravel Integration

### API v3 Endpoints (9 routes)

#### Events API
```php
// routes/api.php
Route::get('/api/v3/events', [LegacyAPIController::class, 'events'])->name('api.v3.events');
Route::get('/api/v3/events/lsl', [LegacyAPIController::class, 'eventsLsl'])->name('api.v3.events.lsl');
Route::get('/api/v3/events/json', [LegacyAPIController::class, 'eventsJson'])->name('api.v3.events.json');
Route::get('/api/v3/events/board.png', [LegacyAPIController::class, 'eventsBoard'])->name('api.v3.events.board');
```

#### Scrup API
```php
// routes/api.php
Route::get('/api/v3/scrup/get-version', [LegacyAPIController::class, 'scrupGetVersion'])->name('api.v3.scrup.version');
Route::post('/api/v3/scrup/register/server', [LegacyAPIController::class, 'scrupRegisterServer'])->name('api.v3.scrup.register.server');
Route::post('/api/v3/scrup/register/script', [LegacyAPIController::class, 'scrupRegisterScript'])->name('api.v3.scrup.register.script');
Route::post('/api/v3/scrup/register/client', [LegacyAPIController::class, 'scrupRegisterClient'])->name('api.v3.scrup.register.client');
```

### Legacy API v2 Endpoints (3 routes)
```php
// routes/api.php
Route::get('/api/v2/events', [LegacyAPIController::class, 'legacyEvents'])->name('api.v2.events');
Route::get('/events.lsl2', [LegacyAPIController::class, 'legacyEvents'])->name('events.lsl2');
Route::get('/events.lsl3', [LegacyAPIController::class, 'legacyEvents'])->name('events.lsl3');
```

### EOL API v1 Endpoints (1 route)
```php
// routes/api.php
Route::get('/events.lsl', [LegacyAPIController::class, 'eolEvents'])->name('events.lsl');
```

### Fallback Routes (4 routes)
```php
// routes/api.php
Route::get('/events.php', [LegacyAPIController::class, 'fallbackEvents'])->name('events.php');
// Query parameter routes handled by middleware
```

## Controller Implementation

```php
<?php
// app/Http/Controllers/LegacyAPIController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class LegacyAPIController extends Controller
{
    protected function callLegacyScript($endpoint, $method = 'GET', $data = [])
    {
        $command = ['php', base_path('legacy/src/bundle/standalone/index.php')];
        
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
```

## Implementation Notes

1. **Route Priority**: Legacy routes should be registered before Laravel's default 404 handler
2. **Query Parameters**: Fallback routes with query params need special handling
3. **Response Headers**: Legacy script sets its own headers (status codes, content-type)
4. **Error Handling**: Process failures should return 500 with legacy error output
5. **Performance**: Process::run() is blocking - consider queue for long operations

## Verification Commands

```bash
# Test all routes
php artisan route:list | grep legacy

# Test specific endpoint
curl http://$APP_URL/api/v3/events/lsl

# Check legacy script works
# Not implemented. Would require legacy build and legacy webserver,
# Let's see that later.
```

## Next Steps

1. Create LegacyAPIController with exact methods above
2. Register all routes in routes/api.php
3. Ensure legacy endpoints are properly routed to legacy app (might trigger errors, but they must come from legacy)
4. Build legacy (or include legacy build process in Laravel build behaviour)
3. Test each endpoint matches legacy behavior exactly
