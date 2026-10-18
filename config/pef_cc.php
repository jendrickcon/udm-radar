<?php
/** Non-secret deployment defaults for the private PEF/CC planning export. */
declare(strict_types=1);

function pefCcConfig(array $overrides = []): array
{
    $root = getenv('PEF_CC_PRIVATE_ROOT') ?: 'C:/ProgramData/UDM-RADAR/private';
    $version = getenv('PEF_CC_TEMPLATE_VERSION') ?: 'v0.2-r6';
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
        'signatories' => [
            'program_head_name' => getenv('PEF_CC_PROGRAM_HEAD_NAME') ?: 'MS. CHRISTINA P. ATAL',
            'program_head_title' => getenv('PEF_CC_PROGRAM_HEAD_TITLE') ?: 'Program Chairperson',
            'dean_name' => getenv('PEF_CC_DEAN_NAME') ?: 'MR. FRANCIS ARLANDO L. ATIENZA',
            'dean_title' => getenv('PEF_CC_DEAN_TITLE') ?: 'Dean',
        ],
    ];
    // Only trusted deployment/test callers can supply overrides. Request data
    // never enters this function; academic capacities/hash/disclosures are fixed.
    $allowed = ['private_root', 'template_path', 'temp_path', 'web_roots', 'generation_timeout', 'max_output_bytes', 'cleanup_age_seconds', 'signatories', 'template_version', 'template_sha256'];
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

/** Immutable, version-specific integrity facts. */
function pefCcTemplateContract(string $version): array
{
    return match ($version) {
        'v0.2-r2' => ['sha256' => 'c1895897710d29672960a7414155627409dd847260236c1774f1f444d9bfed02', 'section_height' => 96.0, 'section_wrap' => true, 'print_area' => 'A1:G80'],
        'v0.2-r3' => ['sha256' => 'c26f8e5d8caf65ab8d061bd87484970b899670a27551d1c1662e98de6ad6df63', 'section_height' => 19.5, 'section_wrap' => false, 'print_area' => 'A1:G80'],
        'v0.2-r4' => ['sha256' => 'f14e66d99d570e8547bdb0059b765c1efaafdf0adeea8c9389cb33ff66d740a0', 'section_height' => 19.5, 'section_wrap' => false, 'print_area' => 'A1:G71'],
        'v0.2-r5' => ['sha256' => '23d3bcaeda7df9f8969721b695f5141244a8c78da380052501fa9542bedb370b', 'section_height' => 19.5, 'section_wrap' => false, 'print_area' => 'A1:G71'],
        'v0.2-r6' => ['sha256' => '5941b7b36c5bbee585f32162397de2cd18e0279171cdd6470578e389b18e43ec', 'section_height' => 19.5, 'section_wrap' => false, 'print_area' => 'A1:G71', 'cc_print_area' => 'A1:O70'],
        default => throw new RuntimeException('The approved planning template is unavailable.'),
    };
}

/** Explicit CLI-only owner review helper. */
function pefCcReviewCandidateConfig(array $overrides = [], string $version = 'v0.2-r6'): array
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('The private template candidate is not activated.');
    }
    $config = pefCcConfig($overrides);
    $config['template_version'] = $version;
    $config['template_sha256'] = pefCcTemplateContract($version)['sha256'];
    $config['template_path'] = $overrides['template_path'] ?? $config['private_root'] . '/templates/pef-cc/' . $version . '/template.xlsx';
    return $config;
}
