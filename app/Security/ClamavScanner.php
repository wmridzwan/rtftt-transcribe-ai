<?php

namespace App\Security;

use Illuminate\Filesystem\FilesystemAdapter;
use Throwable;

/**
 * Self-hosted ClamAV malware scanning (P7-006, D7-04).
 *
 * Daemon socket preferred, TCP loopback fallback. Scans stream in 64 KiB
 * chunks over INSTREAM — full objects are never loaded into PHP memory,
 * so 500 MiB uploads scan within the job/request budget. Verdicts:
 *
 * - clean:           daemon replied `<name>: OK`
 * - infected:        daemon replied `<name>: <signature> FOUND`
 * - unavailable:     no daemon reachable (fail closed downstream)
 * - timeout:         daemon did not answer in time (fail closed)
 * - error:           protocol/refusal problem, incl. non-loopback TCP
 *                    endpoints (fail closed; no third-party egress, ever)
 *
 * The socket factory is injectable so the full six-behavior matrix is
 * testable without a daemon; one real-daemon proof is recorded where
 * the verification environment provides ClamAV (else target-only,
 * flagged per the P7-008 AC2 precedent).
 */
final class ClamavScanner
{
    /** @var list<string> */
    public const LOOPBACK_HOSTS = ['127.0.0.1', '::1', 'localhost'];

    /** EICAR-standard test string (contract-mandated; never real malware). */
    public const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    /** @var callable|null fn(string, int): (resource|false) */
    private $connector;

    /** @param callable|null $connector injectable socket factory (tests) */
    public function __construct(?callable $connector = null)
    {
        $this->connector = $connector;
    }

    public function isEnabled(): bool
    {
        return (bool) config('security.clamav.enabled', false);
    }

    /**
     * @return array{transport: string, target: string}|array{refused: string}
     */
    public function endpoint(): array
    {
        $socket = config('security.clamav.socket');

        if (is_string($socket) && $socket !== '') {
            return ['transport' => 'unix', 'target' => $socket];
        }

        $host = (string) config('security.clamav.host', '127.0.0.1');
        $port = (int) config('security.clamav.port', 3310);

        if (! in_array(strtolower($host), self::LOOPBACK_HOSTS, true)
            && ! (bool) config('security.clamav.allow_non_loopback', false)) {
            return ['refused' => sprintf(
                'ClamAV TCP endpoint "%s" is not loopback and CLAMAV_ALLOW_NON_LOOPBACK is not set; refusing (no third-party media egress).',
                $host
            )];
        }

        return ['transport' => 'tcp', 'target' => $host.':'.$port];
    }

    /**
     * @param  resource  $stream  readable binary stream
     * @return array{verdict: string, engine: string|null, signature_date: string|null, detail: string}
     */
    public function scanStream(mixed $stream, int $sizeBytes): array
    {
        $endpoint = $this->endpoint();

        if (isset($endpoint['refused'])) {
            return $this->verdict('error', null, null, (string) $endpoint['refused']);
        }

        $socket = $this->openSocket((string) $endpoint['target'], (int) config('security.clamav.timeout_seconds', 60));

        if (! is_resource($socket)) {
            return $this->verdict('unavailable', null, null, 'ClamAV daemon unreachable at '.$endpoint['transport'].' '.$endpoint['target']);
        }

        try {
            stream_set_timeout($socket, (int) config('security.clamav.timeout_seconds', 60));
            fwrite($socket, "zINSTREAM\0");

            if (is_resource($stream)) {
                while (! feof($stream)) {
                    $chunk = fread($stream, 65536);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    if (fwrite($socket, pack('N', strlen($chunk)).$chunk) === false) {
                        return $this->verdict('error', null, null, 'ClamAV INSTREAM write failed.');
                    }
                }
            }

            fwrite($socket, pack('N', 0));

            $response = $this->readLine($socket);

            if ($response === null) {
                $meta = stream_get_meta_data($socket);

                if ($meta['timed_out'] === true) {
                    return $this->verdict('timeout', null, null, 'ClamAV scan timed out.');
                }

                return $this->verdict('error', null, null, 'ClamAV daemon gave no reply.');
            }

            if (str_ends_with($response, 'OK')) {
                $health = $this->version();

                return $this->verdict('clean', $health['engine'] ?? null, $health['signature_date'] ?? null, 'OK');
            }

            if (str_ends_with($response, 'FOUND')) {
                $signature = trim((string) preg_replace('/^stream:\s*|\s*FOUND$/', '', $response));

                return $this->verdict('infected', null, null, $signature === '' ? 'FOUND' : $signature);
            }

            return $this->verdict('error', null, null, 'Unrecognized ClamAV reply: '.$response);
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /**
     * Scan a file on a storage disk by streaming it (never full load).
     *
     * @return array{verdict: string, engine: string|null, signature_date: string|null, detail: string}
     */
    public function scanStagedFile(FilesystemAdapter $storage, string $path): array
    {
        try {
            $size = $storage->size($path);
            $stream = $storage->readStream($path);
        } catch (Throwable $exception) {
            return $this->verdict('error', null, null, 'Staged file unreadable: '.$exception->getMessage());
        }

        try {
            if (! is_resource($stream)) {
                return $this->verdict('error', null, null, 'Staged file stream unavailable.');
            }

            return $this->scanStream($stream, (int) $size);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Daemon health: reachability, engine version, signature freshness.
     *
     * @return array{reachable: bool, engine: string|null, signature_date: string|null, signature_age_hours: float|null, stale: bool, detail: string}
     */
    public function health(): array
    {
        $version = $this->version();

        if ($version['raw'] === null) {
            return [
                'reachable' => false, 'engine' => null, 'signature_date' => null,
                'signature_age_hours' => null, 'stale' => true,
                'detail' => $version['detail'],
            ];
        }

        $ageHours = null;
        $stale = false;

        if ($version['signature_date'] !== null) {
            $timestamp = strtotime($version['signature_date']);
            $ageHours = $timestamp === false ? null : (time() - $timestamp) / 3600;
            $stale = $ageHours === null || $ageHours > (int) config('security.clamav.max_signature_age_hours', 48);
        } else {
            $stale = true;
        }

        return [
            'reachable' => true,
            'engine' => $version['engine'],
            'signature_date' => $version['signature_date'],
            'signature_age_hours' => $ageHours,
            'stale' => $stale,
            'detail' => $stale ? 'signatures stale or undated' : 'ok',
        ];
    }

    /**
     * Raw VERSION probe. `VERSION\n` replies e.g.
     * `ClamAV 1.4.2/27345/Thu Sep 25 08:12:01 2026`.
     *
     * @return array{raw: string|null, engine: string|null, signature_date: string|null, detail: string}
     */
    public function version(): array
    {
        $endpoint = $this->endpoint();

        if (isset($endpoint['refused'])) {
            return ['raw' => null, 'engine' => null, 'signature_date' => null, 'detail' => (string) $endpoint['refused']];
        }

        $socket = $this->openSocket((string) $endpoint['target'], 10);

        if (! is_resource($socket)) {
            return ['raw' => null, 'engine' => null, 'signature_date' => null, 'detail' => 'unreachable'];
        }

        try {
            stream_set_timeout($socket, 10);
            fwrite($socket, "VERSION\n");
            $line = $this->readLine($socket);

            if ($line === null || ! str_starts_with($line, 'ClamAV ')) {
                return ['raw' => $line, 'engine' => null, 'signature_date' => null, 'detail' => 'unrecognized VERSION reply'];
            }

            $parts = explode('/', $line, 3);

            return [
                'raw' => $line,
                'engine' => trim(str_replace('ClamAV', '', $parts[0])),
                'signature_date' => isset($parts[2]) ? trim($parts[2]) : null,
                'detail' => 'ok',
            ];
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /** @return resource|false */
    private function openSocket(string $target, int $timeout): mixed
    {
        if ($this->connector !== null) {
            try {
                return ($this->connector)($target, $timeout);
            } catch (Throwable) {
                return false;
            }
        }

        try {
            if (str_starts_with($target, '/')) {
                $socket = @stream_socket_client('unix://'.$target, $errno, $errstr, $timeout);
            } else {
                $socket = @stream_socket_client('tcp://'.$target, $errno, $errstr, $timeout);
            }
        } catch (Throwable) {
            return false;
        }

        return $socket === false ? false : $socket;
    }

    private function readLine(mixed $socket): ?string
    {
        $line = fgets($socket, 4096);

        if ($line === false) {
            return null;
        }

        return trim($line, "\r\n\0");
    }

    /** @return array{verdict: string, engine: string|null, signature_date: string|null, detail: string} */
    private function verdict(string $verdict, ?string $engine, ?string $signatureDate, string $detail): array
    {
        return ['verdict' => $verdict, 'engine' => $engine, 'signature_date' => $signatureDate, 'detail' => $detail];
    }
}
