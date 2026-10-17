<?php
/** Non-secret deployment defaults for the private PEF/CC planning export. */
declare(strict_types=1);

function pefCcConfig(array $overrides = []): array
{
    $root = getenv('PEF_CC_PRIVATE_ROOT') ?: 'C:/ProgramData/UDM-RADAR/private';
    $config = [
        'template_version' => 'v0.2-r2',
        'template_sha256' => 'c1895897710d29672960a7414155627409dd847260236c1774f1f444d9bfed02',
        'private_root' => $root,
        'template_path' => getenv('PEF_CC_TEMPLATE_PATH') ?: $root . '/templates/pef-cc/v0.2-r2/template.xlsx',
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
        'section_max' => 96,
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
