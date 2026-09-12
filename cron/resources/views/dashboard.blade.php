<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Graphite Cron Engine — graphite-v2-cron.alphadirect.co.bw</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .pulse { animation: pulse 2s cubic-bezier(0.4,0,0.6,1) infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b shadow-sm sticky top-0 z-10">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <div>
        <h1 class="text-lg font-bold text-gray-900">Graphite Cron Engine</h1>
        <p class="text-xs text-gray-400">graphite-v2-cron.alphadirect.co.bw</p>
      </div>
    </div>
    <div class="flex items-center gap-4">
      @if($dbStatus['ok'])
        <span class="flex items-center gap-1.5 text-xs text-green-700 bg-green-50 border border-green-200 px-3 py-1.5 rounded-full">
          <span class="w-2 h-2 bg-green-500 rounded-full"></span>
          DB Connected · {{ number_format($dbStatus['policies']) }} policies
        </span>
      @else
        <span class="flex items-center gap-1.5 text-xs text-red-700 bg-red-50 border border-red-200 px-3 py-1.5 rounded-full">
          <span class="w-2 h-2 bg-red-500 rounded-full pulse"></span>
          DB Error
        </span>
      @endif
      <span class="text-xs text-gray-400" id="clock"></span>
    </div>
  </div>
</header>

<div class="max-w-7xl mx-auto px-6 py-8 space-y-8">

  <!-- Job Cards -->
  <div>
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Scheduled Jobs</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      @foreach($jobs as $job)
        @php
          $colors = [
            'blue'   => ['border'=>'border-blue-200',  'icon'=>'bg-blue-100 text-blue-600',   'btn'=>'bg-blue-600 hover:bg-blue-700'],
            'orange' => ['border'=>'border-orange-200','icon'=>'bg-orange-100 text-orange-600','btn'=>'bg-orange-500 hover:bg-orange-600'],
            'red'    => ['border'=>'border-red-200',   'icon'=>'bg-red-100 text-red-600',     'btn'=>'bg-red-600 hover:bg-red-700'],
            'purple' => ['border'=>'border-purple-200','icon'=>'bg-purple-100 text-purple-600','btn'=>'bg-purple-600 hover:bg-purple-700'],
            'indigo' => ['border'=>'border-indigo-200','icon'=>'bg-indigo-100 text-indigo-600','btn'=>'bg-indigo-600 hover:bg-indigo-700'],
          ];
          $c = $colors[$job['color']] ?? $colors['blue'];
          $statusDot = match($job['status']) {
            'ok'      => 'bg-green-500',
            'error'   => 'bg-red-500',
            'running' => 'bg-blue-500 pulse',
            default   => 'bg-gray-300',
          };
        @endphp
        <div class="bg-white rounded-xl shadow-sm border {{ $c['border'] }} border-l-4 p-5">
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full {{ $statusDot }}"></span>
              <span class="text-sm font-semibold text-gray-800">{{ $job['label'] }}</span>
            </div>
            <span class="text-xs text-gray-400 bg-gray-50 border rounded-full px-2 py-0.5">{{ $job['schedule'] }}</span>
          </div>

          <div class="text-xs text-gray-500 space-y-1 mb-4">
            <div class="flex justify-between">
              <span>Last run</span>
              <span class="font-medium text-gray-700">{{ $job['last_run'] ? \Carbon\Carbon::parse($job['last_run'])->diffForHumans() : 'Never' }}</span>
            </div>
            @if($job['elapsed'])
            <div class="flex justify-between">
              <span>Duration</span>
              <span class="font-medium text-gray-700">{{ $job['elapsed'] }}</span>
            </div>
            @endif
            @if(!empty($job['summary']))
              @if(isset($job['summary']['gwp']))
              <div class="flex justify-between">
                <span>GWP</span>
                <span class="font-medium text-blue-700">P{{ number_format($job['summary']['gwp']) }}</span>
              </div>
              @endif
              @if(isset($job['summary']['total_outstanding']))
              <div class="flex justify-between">
                <span>Outstanding</span>
                <span class="font-medium text-orange-700">P{{ number_format($job['summary']['total_outstanding']) }}</span>
              </div>
              @endif
              @if(isset($job['summary']['total_anomalies']))
              <div class="flex justify-between">
                <span>Anomalies</span>
                <span class="font-medium text-red-700">{{ number_format($job['summary']['total_anomalies']) }}</span>
              </div>
              @endif
            @endif
          </div>

          <div class="flex items-center gap-2">
            <!-- Trigger button -->
            <button
              onclick="triggerJob('{{ $job['key'] }}')"
              class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 {{ $c['btn'] }} text-white text-xs font-medium rounded-lg transition"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/>
              </svg>
              Run Now
            </button>

            <!-- Download report if available -->
            @if($job['has_file'])
            <a
              href="/reports/download/{{ $job['filename'] }}"
              download
              class="flex items-center gap-1 px-3 py-1.5 border border-gray-200 text-gray-600 text-xs rounded-lg hover:bg-gray-50 transition"
              title="{{ $job['file_size'] }}"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              {{ $job['file_size'] }}
            </a>
            @endif

            <!-- View logs -->
            <button
              onclick="viewLog('{{ $job['key'] }}')"
              class="px-2.5 py-1.5 border border-gray-200 text-gray-500 text-xs rounded-lg hover:bg-gray-50 transition"
              title="View logs"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
              </svg>
            </button>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <!-- Two column layout -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Recent Runs -->
    <div class="bg-white rounded-xl shadow-sm border">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Recent Runs</h2>
        <span class="text-xs text-gray-400">Last 20</span>
      </div>
      <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
        @forelse($recentRuns as $run)
          <div class="flex items-center gap-3 px-5 py-2.5">
            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $run['status'] === 'ok' ? 'bg-green-500' : ($run['status'] === 'error' ? 'bg-red-500' : 'bg-gray-300') }}"></span>
            <div class="flex-1 min-w-0">
              <span class="text-xs font-medium text-gray-700">{{ $run['job_key'] }}</span>
              <span class="text-xs text-gray-400 ml-2">{{ $run['elapsed'] }}</span>
            </div>
            <span class="text-xs text-gray-400 flex-shrink-0">{{ \Carbon\Carbon::parse($run['created_at'])->diffForHumans() }}</span>
          </div>
        @empty
          <div class="px-5 py-8 text-center text-xs text-gray-400">No runs recorded yet</div>
        @endforelse
      </div>
    </div>

    <!-- Upcoming Schedule -->
    <div class="bg-white rounded-xl shadow-sm border">
      <div class="px-5 py-4 border-b">
        <h2 class="text-sm font-semibold text-gray-700">Upcoming Schedule</h2>
      </div>
      <div class="divide-y divide-gray-50">
        @foreach($nextRuns as $nr)
          <div class="flex items-center justify-between px-5 py-3">
            <span class="text-sm text-gray-700">{{ $nr['label'] }}</span>
            <span class="text-xs text-gray-400 bg-gray-50 border rounded-full px-2.5 py-1">{{ $nr['next'] }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>

</div><!-- /max-w -->

<!-- Log Modal -->
<div id="logModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-end justify-center p-4">
  <div class="bg-gray-900 rounded-xl w-full max-w-4xl max-h-[70vh] flex flex-col">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-700">
      <span class="text-sm font-semibold text-white" id="logTitle">Log</span>
      <button onclick="closeLog()" class="text-gray-400 hover:text-white">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <div id="logContent" class="flex-1 overflow-y-auto p-4 font-mono text-xs text-green-400 leading-relaxed"></div>
  </div>
</div>

<!-- Toast -->
<div id="toast" class="hidden fixed bottom-6 right-6 bg-gray-900 text-white text-sm px-4 py-3 rounded-lg shadow-lg z-50"></div>

<script>
  // Clock
  function tick() {
    document.getElementById('clock').textContent = new Date().toUTCString().replace(' GMT','') + ' UTC';
  }
  tick(); setInterval(tick, 1000);

  // Toast
  function toast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `fixed bottom-6 right-6 text-white text-sm px-4 py-3 rounded-lg shadow-lg z-50 ${isError ? 'bg-red-700' : 'bg-gray-900'}`;
    setTimeout(() => { t.className = t.className + ' hidden'; }, 3500);
  }

  // Trigger job
  async function triggerJob(key) {
    toast(`Starting ${key}...`);
    try {
      const res = await fetch(`/jobs/${key}/trigger`, { method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} });
      const data = await res.json();
      toast(data.message || 'Job triggered');
    } catch(e) {
      toast('Failed to trigger job', true);
    }
  }

  // View log
  async function viewLog(key) {
    document.getElementById('logTitle').textContent = key + ' — last 100 lines';
    document.getElementById('logContent').textContent = 'Loading...';
    document.getElementById('logModal').classList.remove('hidden');
    const res = await fetch(`/logs/${key}`);
    const data = await res.json();
    document.getElementById('logContent').textContent = data.lines.join('\n') || 'No output yet.';
    document.getElementById('logContent').scrollTop = 9999;
  }

  function closeLog() {
    document.getElementById('logModal').classList.add('hidden');
  }
</script>

</body>
</html>
