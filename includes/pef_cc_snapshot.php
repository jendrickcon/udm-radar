<?php
declare(strict_types=1);

require_once __DIR__ . '/pef_cc_view_model.php';

/** Stable object ordering, while preserving the academic/proposed row order. */
function pefCcCanonicalJson(mixed $value): string
{
    $canonicalize = static function (mixed $item) use (&$canonicalize): mixed {
        if ($item instanceof PefCcViewModel) {
            $item = $item->data;
        }
        if (is_array($item)) {
            if (!array_is_list($item)) {
                ksort($item, SORT_STRING);
            }
            foreach ($item as $key => $child) {
                $item[$key] = $canonicalize($child);
            }
        } elseif (is_object($item) || is_resource($item)) {
            throw new InvalidArgumentException('Snapshots contain scalar data only.');
        }
        return $item;
    };
    return json_encode($canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
}

function pefCcSnapshotBase64(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function pefCcSnapshotDecode(string $value): string|false
{
    if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
        return false;
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return $decoded !== false && pefCcSnapshotBase64($decoded) === $value ? $decoded : false;
}

function pefCcSignSnapshot(PefCcViewModel $model, string $key, int $studentId, string $sessionBinding, array $config, ?int $now = null): string
{
    if (strlen($key) !== 32 || $studentId <= 0 || $sessionBinding === '' || !$model->data['exportable'] || $model->data['identity']['id'] !== $studentId
        || $model->data['template_version'] !== $config['template_version'] || $model->data['template_hash'] !== $config['template_sha256']) {
        throw new InvalidArgumentException('A valid Student preview and session signing context are required.');
    }
    $now ??= time();
    $envelope = [
        'v' => 1,
        'digest' => hash('sha256', pefCcCanonicalJson($model)),
        'template_version' => $config['template_version'],
        'template_hash' => $config['template_sha256'],
        'iat' => $now,
        'exp' => $now + (int) $config['snapshot_ttl'],
    ];
    $payload = pefCcSnapshotBase64(pefCcCanonicalJson($envelope));
    // Length-delimited ownership data cannot collide through concatenation.
    $binding = pefCcCanonicalJson(['student' => $studentId, 'session' => $sessionBinding]);
    $mac = hash_hmac('sha256', $payload . '.' . $binding, $key, true);
    return $payload . '.' . pefCcSnapshotBase64($mac);
}

function pefCcVerifySnapshot(string $token, PefCcViewModel $model, string $key, int $studentId, string $sessionBinding, array $config, ?int $now = null): bool
{
    if (strlen($token) > 2048 || strlen($key) !== 32 || $studentId <= 0 || $sessionBinding === '' || !$model->data['exportable'] || $model->data['identity']['id'] !== $studentId) {
        return false;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    [$payload, $signature] = $parts;
    $raw = pefCcSnapshotDecode($payload);
    $mac = pefCcSnapshotDecode($signature);
    if ($raw === false || $mac === false || strlen($mac) !== 32) {
        return false;
    }
    $binding = pefCcCanonicalJson(['student' => $studentId, 'session' => $sessionBinding]);
    if (!hash_equals(hash_hmac('sha256', $payload . '.' . $binding, $key, true), $mac)) {
        return false;
    }
    try {
        $envelope = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        $now ??= time();
        if (!is_array($envelope) || array_keys($envelope) !== ['digest', 'exp', 'iat', 'template_hash', 'template_version', 'v']
            || $envelope['v'] !== 1 || !is_int($envelope['iat']) || !is_int($envelope['exp'])
            || $envelope['iat'] > $now || $envelope['exp'] <= $now
            || $envelope['exp'] - $envelope['iat'] !== (int) $config['snapshot_ttl']
            || $envelope['template_version'] !== $config['template_version']
            || $envelope['template_hash'] !== $config['template_sha256']
            || !is_string($envelope['digest'])) {
            return false;
        }
        return hash_equals($envelope['digest'], hash('sha256', pefCcCanonicalJson($model)));
    } catch (JsonException | InvalidArgumentException $exception) {
        return false;
    }
}
