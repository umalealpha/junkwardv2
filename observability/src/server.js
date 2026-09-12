const express = require('express');
const { MongoClient } = require('mongodb');
const cors = require('cors');
const compression = require('compression');
const path = require('path');

const app = express();
app.use(cors());
app.use(compression());
app.use(express.json());
app.use(express.static(path.join(__dirname, '..', 'public')));

const MONGO_URI = process.env.MONGODB_URI || `mongodb://${process.env.MONGODB_HOST || '127.0.0.1'}:${process.env.MONGODB_PORT || 27017}`;
const DB_NAME = process.env.MONGODB_DATABASE || 'graphite_observability';

let db;

async function connectDB() {
  const client = new MongoClient(MONGO_URI);
  await client.connect();
  db = client.db(DB_NAME);
  console.log(`Connected to MongoDB: ${DB_NAME}`);
}

// ── Dashboard Stats ─────────────────────────────────────────
app.get('/api/stats', async (req, res) => {
  try {
    const now = new Date();
    const hour = new Date(now - 3600000);
    const day = new Date(now - 86400000);

    const incoming = db.collection('api_requests_incoming');
    const outgoing = db.collection('api_requests_outgoing');

    const [totalIncoming, totalOutgoing, lastHour, lastDay, avgDuration, errorCount, topEndpoints, recentSlow] = await Promise.all([
      incoming.countDocuments(),
      outgoing.countDocuments(),
      incoming.countDocuments({ timestamp: { $gte: hour } }),
      incoming.countDocuments({ timestamp: { $gte: day } }),
      incoming.aggregate([
        { $match: { timestamp: { $gte: day } } },
        { $group: { _id: null, avg: { $avg: '$duration_ms' }, p95: { $percentile: { input: '$duration_ms', p: [0.95], method: 'approximate' } } } }
      ]).toArray(),
      incoming.countDocuments({ timestamp: { $gte: day }, status_code: { $gte: 400 } }),
      incoming.aggregate([
        { $match: { timestamp: { $gte: day } } },
        { $group: { _id: '$url', count: { $sum: 1 }, avgMs: { $avg: '$duration_ms' } } },
        { $sort: { count: -1 } },
        { $limit: 10 }
      ]).toArray(),
      incoming.find({ duration_ms: { $gt: 5000 }, timestamp: { $gte: day } }).sort({ timestamp: -1 }).limit(10).toArray()
    ]);

    res.json({
      total: { incoming: totalIncoming, outgoing: totalOutgoing },
      last_hour: lastHour,
      last_24h: lastDay,
      avg_duration_ms: avgDuration[0]?.avg ? Math.round(avgDuration[0].avg) : 0,
      p95_duration_ms: avgDuration[0]?.p95?.[0] ? Math.round(avgDuration[0].p95[0]) : 0,
      errors_24h: errorCount,
      error_rate: lastDay > 0 ? ((errorCount / lastDay) * 100).toFixed(2) + '%' : '0%',
      top_endpoints: topEndpoints,
      slow_requests: recentSlow.map(r => ({ url: r.url, method: r.method, duration_ms: r.duration_ms, status: r.status_code, time: r.timestamp })),
    });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── Request Search ──────────────────────────────────────────
app.get('/api/requests', async (req, res) => {
  try {
    const { direction = 'incoming', page = 1, limit = 50, method, url, status, ip, min_duration, max_duration, from, to, search } = req.query;

    const collection = direction === 'outgoing' ? 'api_requests_outgoing' : 'api_requests_incoming';
    const filter = {};

    if (method) filter.method = method.toUpperCase();
    if (url) filter.url = { $regex: url, $options: 'i' };
    if (status) filter.status_code = parseInt(status);
    if (ip) filter.ip_address = { $regex: ip };
    if (min_duration) filter.duration_ms = { ...filter.duration_ms, $gte: parseInt(min_duration) };
    if (max_duration) filter.duration_ms = { ...filter.duration_ms, $lte: parseInt(max_duration) };
    if (from) filter.timestamp = { ...filter.timestamp, $gte: new Date(from) };
    if (to) filter.timestamp = { ...filter.timestamp, $lte: new Date(to) };
    if (search) filter.$text = { $search: search };

    const skip = (parseInt(page) - 1) * parseInt(limit);

    const [data, total] = await Promise.all([
      db.collection(collection).find(filter).sort({ timestamp: -1 }).skip(skip).limit(parseInt(limit)).toArray(),
      db.collection(collection).countDocuments(filter),
    ]);

    res.json({
      data,
      pagination: {
        page: parseInt(page),
        limit: parseInt(limit),
        total,
        pages: Math.ceil(total / parseInt(limit)),
      },
    });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── Time Series (for charts) ────────────────────────────────
app.get('/api/timeseries', async (req, res) => {
  try {
    const { hours = 24, interval = 'hour' } = req.query;
    const since = new Date(Date.now() - parseInt(hours) * 3600000);

    const groupBy = interval === 'minute'
      ? { $dateToString: { format: '%Y-%m-%dT%H:%M:00Z', date: '$timestamp' } }
      : { $dateToString: { format: '%Y-%m-%dT%H:00:00Z', date: '$timestamp' } };

    const data = await db.collection('api_requests_incoming').aggregate([
      { $match: { timestamp: { $gte: since } } },
      { $group: {
        _id: groupBy,
        count: { $sum: 1 },
        avg_ms: { $avg: '$duration_ms' },
        errors: { $sum: { $cond: [{ $gte: ['$status_code', 400] }, 1, 0] } },
      }},
      { $sort: { _id: 1 } },
    ]).toArray();

    res.json(data.map(d => ({
      time: d._id,
      requests: d.count,
      avg_ms: Math.round(d.avg_ms),
      errors: d.errors,
    })));
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── Alerts ──────────────────────────────────────────────────
app.get('/api/alerts', async (req, res) => {
  try {
    const now = new Date();
    const fiveMin = new Date(now - 300000);
    const alerts = [];

    // Check for slow requests (> 5s in last 5 min)
    const slow = await db.collection('api_requests_incoming').countDocuments({
      timestamp: { $gte: fiveMin }, duration_ms: { $gt: 5000 }
    });
    if (slow > 0) {
      alerts.push({ type: 'warning', message: `${slow} slow requests (>5s) in last 5 minutes`, count: slow });
    }

    // Check for high error rate
    const [total5m, errors5m] = await Promise.all([
      db.collection('api_requests_incoming').countDocuments({ timestamp: { $gte: fiveMin } }),
      db.collection('api_requests_incoming').countDocuments({ timestamp: { $gte: fiveMin }, status_code: { $gte: 500 } }),
    ]);
    const errorRate = total5m > 0 ? (errors5m / total5m) * 100 : 0;
    if (errorRate > 10) {
      alerts.push({ type: 'critical', message: `Error rate at ${errorRate.toFixed(1)}% (${errors5m}/${total5m}) in last 5 minutes`, rate: errorRate });
    }

    // Check for outgoing failures
    const outFail = await db.collection('api_requests_outgoing').countDocuments({
      timestamp: { $gte: fiveMin }, status_code: { $gte: 400 }
    });
    if (outFail > 0) {
      alerts.push({ type: 'warning', message: `${outFail} failed outgoing requests in last 5 minutes`, count: outFail });
    }

    res.json({ alerts, checked_at: now });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── Archive Search ──────────────────────────────────────────
app.get('/api/archive', async (req, res) => {
  try {
    const { page = 1, limit = 50, url, from, to } = req.query;
    const filter = {};
    if (url) filter.url = { $regex: url, $options: 'i' };
    if (from) filter.timestamp = { ...filter.timestamp, $gte: new Date(from) };
    if (to) filter.timestamp = { ...filter.timestamp, $lte: new Date(to) };

    const skip = (parseInt(page) - 1) * parseInt(limit);
    const [data, total] = await Promise.all([
      db.collection('api_requests_archive').find(filter).sort({ timestamp: -1 }).skip(skip).limit(parseInt(limit)).toArray(),
      db.collection('api_requests_archive').countDocuments(filter),
    ]);

    res.json({ data, pagination: { page: parseInt(page), limit: parseInt(limit), total, pages: Math.ceil(total / parseInt(limit)) } });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── Export CSV ───────────────────────────────────────────────
app.get('/api/export', async (req, res) => {
  try {
    const { direction = 'incoming', method, url, from, to, limit = 1000 } = req.query;
    const collection = direction === 'outgoing' ? 'api_requests_outgoing' : 'api_requests_incoming';
    const filter = {};
    if (method) filter.method = method.toUpperCase();
    if (url) filter.url = { $regex: url, $options: 'i' };
    if (from) filter.timestamp = { $gte: new Date(from) };
    if (to) filter.timestamp = { ...filter.timestamp, $lte: new Date(to) };

    const data = await db.collection(collection).find(filter).sort({ timestamp: -1 }).limit(parseInt(limit)).toArray();

    const header = 'timestamp,method,url,status_code,duration_ms,ip_address,user_id\n';
    const rows = data.map(d => `${d.timestamp},${d.method},${d.url},${d.status_code},${d.duration_ms},${d.ip_address},${d.user_id}`).join('\n');

    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename=api_requests.csv');
    res.send(header + rows);
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── SPA fallback ────────────────────────────────────────────
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});

const PORT = process.env.PORT || 3001;
connectDB().then(() => {
  app.listen(PORT, () => console.log(`Observability portal running on port ${PORT}`));
}).catch(e => {
  console.error('MongoDB connection failed:', e.message);
  process.exit(1);
});
