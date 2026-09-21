// P5-006 deterministic translation-worker TEST DOUBLE (verification tooling, ADR-021).
//
// Speaks the real worker /translate contract so the Laravel job, HTTP provider,
// response validator, and writer all run for real; only the model is faked.
// This does NOT satisfy the real provider/model gate (P5-008, D5-09).
//
// Markers in source text steer behaviour:
//   [[SLOW]]     answer after a delay (lets the browser observe "translating")
//   [[FAKE_401]] answer 401 (drives the non-retryable CONFIGURATION_ERROR path)
import http from 'node:http';

const port = Number(process.env.P5_006_WORKER_PORT ?? 8124);
const token = process.env.P5_006_WORKER_TOKEN ?? 'verification-token';
const slowMs = Number(process.env.P5_006_SLOW_MS ?? 7000);

const dictionary = {
    'Good morning everyone': { ms: 'Selamat pagi semua orang', en: 'Good morning everyone', zh: '大家早上好', ta: 'அனைவருக்கும் காலை வணக்கம்' },
    'Welcome to the weekly meeting': { ms: 'Selamat datang ke mesyuarat mingguan', en: 'Welcome to the weekly meeting', zh: '欢迎参加每周会议', ta: 'வாராந்திர கூட்டத்திற்கு வரவேற்கிறோம்' },
    'Thank you for joining': { ms: 'Terima kasih kerana menyertai', en: 'Thank you for joining', zh: '感谢您的参与', ta: 'இணைந்ததற்கு நன்றி' },
};

function translate(text, target) {
    const clean = text.replace('[[SLOW]] ', '').replace('[[FAKE_401]] ', '');
    return dictionary[clean]?.[target] ?? `[${target}] ${clean}`;
}

const server = http.createServer((req, res) => {
    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', double: true }));
        return;
    }

    if (req.method !== 'POST' || req.url !== '/translate') {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ detail: 'Not Found' }));
        return;
    }

    let raw = '';
    req.on('data', (chunk) => (raw += chunk));
    req.on('end', () => {
        if (req.headers.authorization !== `Bearer ${token}`) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ detail: 'Unauthorized' }));
            return;
        }

        let body;
        try {
            body = JSON.parse(raw);
        } catch {
            res.writeHead(422, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ detail: 'Invalid JSON' }));
            return;
        }

        const joined = (body.segments ?? []).map((s) => s.text).join(' ');

        if (joined.includes('[[FAKE_401]]')) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ detail: 'Unauthorized' }));
            return;
        }

        const respond = () => {
            const segments = (body.segments ?? []).map((segment) => ({
                segment_index: segment.segment_index,
                start_seconds: segment.start_seconds,
                end_seconds: segment.end_seconds,
                text: translate(segment.text, body.target_language),
                source_language: segment.source_language,
            }));

            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({
                contract_version: '1.0',
                target_language: body.target_language,
                provider: 'self-hosted',
                model: 'p5-006-test-double',
                text: segments.map((s) => s.text).join(' '),
                segments,
            }));
        };

        if (joined.includes('[[SLOW]]')) {
            setTimeout(respond, slowMs);
        } else {
            respond();
        }
    });
});

server.listen(port, '127.0.0.1', () => {
    console.log(`p5-006 fake translation worker (test double) listening on ${port}`);
});
