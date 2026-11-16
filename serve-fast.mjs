import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const PUBLIC_DIR = path.join(ROOT, 'public');
const SERVER_PHP = path.join(ROOT, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php');
const PORT = Number(process.env.PORT || 8000);
const WORKER_PORTS = [8011, 8012, 8013, 8014];

const MIME = {
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.mjs': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.webp': 'image/webp',
    '.gif': 'image/gif',
    '.ico': 'image/x-icon',
    '.woff2': 'font/woff2',
    '.woff': 'font/woff',
    '.txt': 'text/plain; charset=utf-8',
};

const workers = WORKER_PORTS.map((port) => ({ port, busy: 0, proc: null }));
let shuttingDown = false;

function spawnWorker(w) {
    const proc = spawn(
        'php',
        [
            '-d', 'zend_extension=opcache',
            '-d', 'opcache.enable=1',
            '-d', 'opcache.enable_cli=1',
            '-d', 'opcache.memory_consumption=128',
            '-d', 'realpath_cache_size=4096K',
            '-d', 'realpath_cache_ttl=600',
            '-d', 'max_execution_time=120',
            '-S', `127.0.0.1:${w.port}`,
            SERVER_PHP,
        ],
        {
            cwd: PUBLIC_DIR,
            stdio: ['ignore', 'ignore', 'pipe'],
            windowsHide: true,
        }
    );
    w.proc = proc;
    proc.stderr.on('data', () => {});
    proc.on('exit', () => {
        if (!shuttingDown) {
            setTimeout(() => spawnWorker(w), 500);
        }
    });
}

workers.forEach(spawnWorker);

function pickWorker() {
    let best = workers[0];
    for (const w of workers) {
        if (w.busy < best.busy) best = w;
    }
    return best;
}

function tryServeStatic(req, res) {
    if (req.method !== 'GET' && req.method !== 'HEAD') return false;
    const rawPath = decodeURIComponent((req.url || '/').split('?')[0]);
    if (rawPath === '/' || rawPath.endsWith('.php')) return false;

    const safePath = path.normalize(path.join(PUBLIC_DIR, rawPath));
    if (!safePath.startsWith(PUBLIC_DIR)) return false;

    try {
        const stat = fs.statSync(safePath);
        if (!stat.isFile()) return false;
        const ext = path.extname(safePath).toLowerCase();
        res.writeHead(200, {
            'Content-Type': MIME[ext] || 'application/octet-stream',
            'Content-Length': stat.size,
            'Cache-Control': rawPath.startsWith('/build/') ? 'public, max-age=31536000, immutable' : 'public, max-age=86400',
        });
        if (req.method === 'HEAD') {
            res.end();
        } else {
            fs.createReadStream(safePath).pipe(res);
        }
        return true;
    } catch {
        return false;
    }
}

const server = http.createServer((req, res) => {
    if (tryServeStatic(req, res)) return;

    const worker = pickWorker();
    worker.busy++;
    let released = false;
    const release = () => {
        if (!released) {
            released = true;
            worker.busy = Math.max(0, worker.busy - 1);
        }
    };

    const headers = { ...req.headers, connection: 'close' };
    const proxyReq = http.request(
        {
            hostname: '127.0.0.1',
            port: worker.port,
            path: req.url,
            method: req.method,
            headers,
        },
        (proxyRes) => {
            res.writeHead(proxyRes.statusCode || 500, proxyRes.headers);
            proxyRes.pipe(res);
            proxyRes.on('end', release);
            proxyRes.on('close', release);
        }
    );

    proxyReq.on('error', (err) => {
        release();
        if (!res.headersSent) {
            res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
        }
        res.end(`Upstream PHP worker error: ${err.message}`);
    });

    req.pipe(proxyReq);
    res.on('close', release);
});

server.keepAliveTimeout = 65000;
server.listen({ port: PORT, host: '::', ipv6Only: false }, () => {
    console.log(`[FastKPI] Dual-stack server listening on http://localhost:${PORT} (4 PHP workers + 30m auto-sync)`);
});

// Built-in 30-minute background KPI sync (single process — no extra scheduler task needed)
let syncProc = null;
const SYNC_INTERVAL_MS = 30 * 60 * 1000;
const syncTimer = setInterval(() => {
    if (shuttingDown || syncProc) return;
    syncProc = spawn('php', [path.join(ROOT, 'artisan'), 'kpi:sync', '--days=2'], {
        cwd: ROOT,
        stdio: 'ignore',
        windowsHide: true,
    });
    syncProc.on('exit', () => {
        syncProc = null;
    });
}, SYNC_INTERVAL_MS);
syncTimer.unref();

function cleanup() {
    shuttingDown = true;
    clearInterval(syncTimer);
    try { syncProc?.kill(); } catch {}
    for (const w of workers) {
        try { w.proc?.kill(); } catch {}
    }
    process.exit(0);
}
process.on('SIGINT', cleanup);
process.on('SIGTERM', cleanup);
