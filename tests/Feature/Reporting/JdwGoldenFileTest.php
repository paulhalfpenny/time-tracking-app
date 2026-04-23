<?php

use App\Domain\Reporting\JdwReportQuery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\JdwProjectMetadataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Tolerance for floating-point hour comparisons
const HOUR_TOLERANCE = 0.01;

function assertHours(?float $actual, ?float $expected, string $label): void
{
    if ($expected === null || $expected === 0.0) {
        expect($actual)->toBeNull("{$label} should be null for zero hours");

        return;
    }

    expect($actual)->not->toBeNull("{$label} should have hours");
    expect(abs((float) $actual - $expected))->toBeLessThanOrEqual(HOUR_TOLERANCE, "{$label}: expected {$expected}, got {$actual}");
}

beforeEach(function (): void {
    // All 47 unique users appearing across the three March 2026 Harvest fixture CSVs.
    // harvest:import requires users to exist — it throws on an unknown name.
    $users = [
        'Aled Brown', 'Andy Clark', 'Ashley Packwood', 'Beckie Dinner', 'Bolu Oyewale',
        'Brent Heyes', 'Chris Murfin', 'Colin Williamson', 'Dan Pringle', 'Darryl Smith',
        'Deepthi Bandaru', 'Duncan Buckle', 'Elvis Mokom', 'Emma Robinson', 'Emma Tomlinson',
        'Emmanuel Boateng Arkoh', 'Fatima Igot', 'Gerhard Van Wyk', 'Guy Hillary',
        'Haritha Yerukonda', 'Henry Bevan', 'Ian Harris', 'Jo Ryall',
        'Jubel (Mohammed) Mahboob', 'Ksenia Khristus', 'Lech Boron', 'Marc Gilliatt',
        'Mark Lawrence', 'Martin Glover', 'Martin Reeves', 'Oli Janes', 'Oliver Morrison',
        'Paul Halfpenny', 'Paul Messer', 'Phil Dempsey', 'Ranjani Narayanan', 'Rey Royeras',
        'Robert Meacher', 'Sarah Vaughan', 'Shumyla Farid', 'Steve Jones', 'Sushant Chatufale',
        'Tanya Ryan', 'Thomas McGregor', 'Tom Staunton', 'Will Sandford', 'Yamuna Simhadri',
    ];

    foreach ($users as $name) {
        User::factory()->create(['name' => $name]);
    }

    $base = base_path('tests/Fixtures/jdw-march26');
    $this->artisan('harvest:import', ['path' => "{$base}/programme.csv"])->assertSuccessful();
    $this->artisan('harvest:import', ['path' => "{$base}/projects.csv"])->assertSuccessful();
    $this->artisan('harvest:import', ['path' => "{$base}/sm.csv"])->assertSuccessful();

    $this->seed(JdwProjectMetadataSeeder::class);
});

// ── Programme Management block ───────────────────────────────────────────────

it('programme block: Planning hours match', function (): void {
    $row = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->programmeRow();
    assertHours($row['Planning'], 24.75, 'Planning');
});

it('programme block: Project Management hours include aliased Meeting entries', function (): void {
    $row = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->programmeRow();
    assertHours($row['Project Management, Meetings & Reporting'], 231.44, 'Programme PM&R');
});

it('programme block: Admin hours match', function (): void {
    $row = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->programmeRow();
    assertHours($row['Admin'], 27.22, 'Admin');
});

it('programme block: leave and absence hours match', function (): void {
    $row = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->programmeRow();
    assertHours($row['Holiday'], 395.5, 'Holiday');
    assertHours($row['Sick'], 36.5, 'Sick');
    assertHours($row['Other Absence'], 260.32, 'Other Absence');
    assertHours($row['Training'], 13.53, 'Training');
    assertHours($row['Finance'], 13.0, 'Finance');
    assertHours($row['HR'], 48.0, 'HR');
    assertHours($row['Recruitment'], 1.0, 'Recruitment');
    assertHours($row['Travel'], 21.5, 'Travel');
    assertHours($row['Break'], 23.0, 'Break');
    assertHours($row['Lunch'], 711.75, 'Lunch');
    assertHours($row['Systems Admin'], 7.91, 'Systems Admin');
});

it('programme block: Bank Holiday is null (no Bank Holiday task in March 2026 Harvest export)', function (): void {
    $row = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->programmeRow();
    expect($row['Bank Holiday'])->toBeNull();
});

// ── Projects block ───────────────────────────────────────────────────────────

it('projects block: Customer App / PWA / App Manager hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->projectsRows();
    $project = $rows->firstWhere('name', 'Customer App / PWA / App Manager');
    expect($project)->not->toBeNull('Customer App / PWA / App Manager not found in projects block');

    assertHours($project['hours']['Development'], 607.29, 'CAP Development');
    assertHours($project['hours']['Project Management, Meetings & Reporting'], 264.15, 'CAP PM&R');
    assertHours($project['hours']['Testing'], 363.0, 'CAP Testing');
    assertHours($project['hours']['Planning'], 78.67, 'CAP Planning');
    assertHours($project['hours']['Systems Admin'], 70.89, 'CAP Systems Admin');
    assertHours($project['hours']['Design'], 2.25, 'CAP Design');
    assertHours($project['hours']['Release'], 30.5, 'CAP Release');
});

it('projects block: mySchedule hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->projectsRows();
    $project = $rows->firstWhere('name', 'mySchedule');
    expect($project)->not->toBeNull('mySchedule not found in projects block');

    assertHours($project['hours']['Development'], 296.37, 'mySchedule Development');
    assertHours($project['hours']['Project Management, Meetings & Reporting'], 87.31, 'mySchedule PM&R');
    assertHours($project['hours']['Testing'], 112.75, 'mySchedule Testing');
    assertHours($project['hours']['Planning'], 170.0, 'mySchedule Planning');
    expect($project['hours']['Systems Admin'])->toBeNull('mySchedule Systems Admin should be null');
    assertHours($project['hours']['Design'], 5.0, 'mySchedule Design');
    assertHours($project['hours']['Release'], 0.5, 'mySchedule Release');
});

it('projects block: myJDW hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->projectsRows();
    $project = $rows->firstWhere('name', 'myJDW');
    expect($project)->not->toBeNull('myJDW not found in projects block');

    assertHours($project['hours']['Development'], 34.25, 'myJDW Development');
    assertHours($project['hours']['Project Management, Meetings & Reporting'], 68.25, 'myJDW PM&R');
    assertHours($project['hours']['Testing'], 114.75, 'myJDW Testing');
    assertHours($project['hours']['Planning'], 9.5, 'myJDW Planning');
    assertHours($project['hours']['Design'], 7.18, 'myJDW Design');
});

it('projects block: rows are sorted by jdw_sort_order', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->projectsRows();
    $orders = $rows->pluck('jdw_sort_order')->filter()->values()->all();
    expect($orders)->toBe(collect($orders)->sort()->values()->all());
});

// ── Support & Maintenance block ──────────────────────────────────────────────

it('S&M block: CIS Support & Maintenance hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->smRows();
    $project = $rows->firstWhere('name', 'CIS Support & Maintenance');
    expect($project)->not->toBeNull('CIS Support & Maintenance not found in S&M block');

    assertHours($project['hours']['Customer Support'], 9.17, 'CIS Customer Support');
    expect($project['hours']['Maintenance'])->toBeNull();
});

it('S&M block: Customer App / PWA Support & Maintenance hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->smRows();
    $project = $rows->firstWhere('name', 'Customer App / PWA / App Manager Support & Maintenance');
    expect($project)->not->toBeNull();

    assertHours($project['hours']['Customer Support'], 120.07, 'CAP S&M Customer Support');
    assertHours($project['hours']['Maintenance'], 44.5, 'CAP S&M Maintenance');
});

it('S&M block: myJDW Support & Maintenance hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->smRows();
    $project = $rows->firstWhere('name', 'myJDW Support & Maintenance');
    expect($project)->not->toBeNull();

    assertHours($project['hours']['Customer Support'], 44.1, 'myJDW S&M Customer Support');
    assertHours($project['hours']['Maintenance'], 18.83, 'myJDW S&M Maintenance');
});

it('S&M block: mySchedule Support & Maintenance hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->smRows();
    $project = $rows->firstWhere('name', 'mySchedule Support & Maintenance');
    expect($project)->not->toBeNull();

    assertHours($project['hours']['Customer Support'], 28.27, 'mySchedule S&M Customer Support');
    assertHours($project['hours']['Maintenance'], 6.75, 'mySchedule S&M Maintenance');
});

it('S&M block: Support for Franchises hours include aliased Customer support entries', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->smRows();
    $project = $rows->firstWhere('name', 'Support for Franchises');
    expect($project)->not->toBeNull();

    // CSV has "Customer support" (lowercase) which aliases to "Customer Support"
    assertHours($project['hours']['Customer Support'], 6.32, 'Franchises Customer Support');
    assertHours($project['hours']['Project Management, Meetings & Reporting'], 36.5, 'Franchises PM&R');
    assertHours($project['hours']['Maintenance'], 2.25, 'Franchises Maintenance');
});

it('S&M block: WordPress Sites Support & Maintenance hours match', function (): void {
    $rows = (new JdwReportQuery(CarbonImmutable::parse('2026-03-01')))->smRows();
    $project = $rows->firstWhere('name', 'WordPress Sites Support & Maintenance');
    expect($project)->not->toBeNull();

    assertHours($project['hours']['Customer Support'], 3.83, 'WP Customer Support');
    assertHours($project['hours']['Maintenance'], 0.83, 'WP Maintenance');
});

// ── Cross-block: no data bleeds between months ───────────────────────────────

it('returns no hours for a month with no data', function (): void {
    $query = new JdwReportQuery(CarbonImmutable::parse('2025-01-01'));

    $progRow = $query->programmeRow();
    expect(array_filter($progRow, fn ($v) => $v !== null))->toBeEmpty();

    $projRows = $query->projectsRows();
    foreach ($projRows as $project) {
        expect(array_filter($project['hours'], fn ($v) => $v !== null))->toBeEmpty();
    }
});
