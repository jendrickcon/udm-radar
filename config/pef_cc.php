<?php
/** Non-secret deployment defaults for the private PEF/CC planning export. */
declare(strict_types=1);

function pefCcConfig(array $overrides = []): array
{
    $root = getenv('PEF_CC_PRIVATE_ROOT') ?: 'C:/ProgramData/UDM-RADAR/private';
    $version = getenv('PEF_CC_TEMPLATE_VERSION') ?: 'v0.2-r3';
    $contract = pefCcTemplateContract($version);
    $config = [
        'template_version' => $version,
        'template_sha256' => $contract['sha256'],
        'private_root' => $root,
        'template_path' => getenv('PEF_CC_TEMPLATE_PATH') ?: $root . '/templates/pef-cc/' . $version . '/template.xlsx',
        'temp_path' => getenv('PEF_CC_TEMP_PATH') ?: $root . '/tmp/pef-cc',
        'web_roots' => array_values(array_filter([
            dirname(__DIR__),
            realpath('C:/xampp/htdocs') ?: 'C:/xampp/htdocs',
            $_SERVER['DOCUMENT_ROOT'] ?? '',
        ])),
        'max_recorded' => 13,
        'max_proposed' => 12,
        'snapshot_ttl' => 600,
        'max_request_bytes' => 16384,
        // Project print-review bounds, not institutional identity/catalogue rules.
        // Native Excel's separate 8pt gate must pass before owner acceptance.
        'printable' => [
            'version' => 'sd-section-membership-wp1.3', 'review_floor_points' => 8,
            'name' => 60, 'student_number' => 20,
            'recorded_code' => 6, 'recorded_title' => 46,
            'proposed_code' => 6, 'proposed_title' => 46, 'section' => 8,
        ],
        'generation_timeout' => 30,
        'max_output_bytes' => 5242880,
        'cleanup_age_seconds' => 600,
        'disclosure' => 'Student-prepared enrolment planning copy based on records available in UDM-RADAR. Proposed subjects require review by the designated academic evaluator before submission.',
        'prototype_disclosure' => 'Prototype performance on synthetic academic records',
    ];
    // Only trusted deployment/test callers can supply overrides. Request data
    // never enters this function; academic capacities/hash/disclosures are fixed.
    $allowed = ['private_root', 'template_path', 'temp_path', 'web_roots', 'generation_timeout', 'max_output_bytes', 'cleanup_age_seconds'];
    if (array_diff(array_keys($overrides), $allowed)) {
        throw new InvalidArgumentException('Unsupported PEF/CC configuration key.');
    }
    $webRoots = $config['web_roots'];
    $config = array_replace($config, $overrides);
    $config['web_roots'] = array_values(array_unique(array_merge($webRoots, $config['web_roots'])));
    foreach (['generation_timeout', 'max_output_bytes', 'cleanup_age_seconds'] as $key) {
        if (!is_int($config[$key]) || $config[$key] < 1) {
            throw new InvalidArgumentException('Invalid PEF/CC runtime limit.');
        }
    }
    return $config;
}

/** Immutable, version-specific integrity facts. r3 is an unactivated review candidate. */
function pefCcTemplateContract(string $version): array
{
    return match ($version) {
        'v0.2-r2' => ['sha256' => 'c1895897710d29672960a7414155627409dd847260236c1774f1f444d9bfed02', 'section_height' => 96.0, 'section_wrap' => true],
        'v0.2-r3' => ['sha256' => 'c26f8e5d8caf65ab8d061bd87484970b899670a27551d1c1662e98de6ad6df63', 'section_height' => 19.5, 'section_wrap' => false],
        default => throw new RuntimeException('The approved planning template is unavailable.'),
    };
}

/** Explicit CLI-only owner review. HTTP configuration continues to use r2. */
function pefCcReviewCandidateConfig(array $overrides = []): array
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('The private template candidate is not activated.');
    }
    $config = pefCcConfig($overrides);
    $config['template_version'] = 'v0.2-r3';
    $config['template_sha256'] = pefCcTemplateContract('v0.2-r3')['sha256'];
    $config['template_path'] = $overrides['template_path'] ?? $config['private_root'] . '/templates/pef-cc/v0.2-r3/template.xlsx';
    return $config;
}
