<?php
declare(strict_types=1);
require_once __DIR__ . '/../fixtures/analytics_mvp_fixture.php';
$checks = 0;
foreach (['', 'udm_radar', 'udm_radar_scratch', 'udm_radar_analytics_mvp_',
    'udm_radar_analytics_mvp_123', 'other_0123456789abcdef',
    'udm_radar_analytics_mvp_0123456789abcdef;DROP DATABASE udm_radar'] as $name) {
    try {
        analyticsFixtureName($name);
        throw new LogicException('Unsafe database name accepted');
    } catch (RuntimeException $expected) { $checks++; }
}
$valid = 'udm_radar_analytics_mvp_0123456789abcdef';
if (analyticsFixtureName($valid) !== $valid) throw new LogicException('Valid owned namespace rejected');
$checks++;
echo "SUMMARY: $checks guard checks passed; no database accessed.\n";
