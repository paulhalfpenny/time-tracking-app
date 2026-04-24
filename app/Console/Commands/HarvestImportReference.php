<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HarvestImportReference extends Command
{
    protected $signature = 'harvest:import-reference
        {path : Path to the Harvest detailed-time CSV file}
        {--dry-run : Parse and validate without writing to the database}';

    protected $description = 'Import clients, projects, tasks, and project-task links from a Harvest CSV (no time entries)';

    /** @var array<string, int> */
    private array $clientCache = [];

    /** @var array<string, int> */
    private array $projectCache = [];

    /** @var array<string, int> */
    private array $taskCache = [];

    private int $clientsCreated = 0;

    private int $clientsExisting = 0;

    private int $projectsCreated = 0;

    private int $projectsExisting = 0;

    private int $tasksCreated = 0;

    private int $tasksExisting = 0;

    private int $errors = 0;

    /** @var array<int, array<int, bool>> project_id -> task_id -> is_billable */
    private array $projectTaskLinks = [];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[dry-run] No changes will be written.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Cannot open file: {$path}");

            return self::FAILURE;
        }

        // Skip header row
        fgetcsv($handle);

        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($row = fgetcsv($handle)) !== false) {
            $bar->advance();

            if (count($row) < 8) {
                $this->errors++;

                continue;
            }

            try {
                $this->processRow($row, $dryRun);
            } catch (\RuntimeException $e) {
                $bar->clear();
                $this->error($e->getMessage());
                $bar->display();
                $this->errors++;
            }
        }

        fclose($handle);
        $bar->finish();
        $this->newLine();

        // Bulk-insert project-task links
        if (! $dryRun) {
            $links = [];
            foreach ($this->projectTaskLinks as $projectId => $tasks) {
                foreach ($tasks as $taskId => $isBillable) {
                    $links[] = [
                        'project_id' => $projectId,
                        'task_id' => $taskId,
                        'is_billable' => $isBillable,
                    ];
                }
            }

            if (! empty($links)) {
                DB::table('project_task')->insertOrIgnore($links);
            }

            $linksCreated = count($links);
        } else {
            $linksCreated = 0;
            foreach ($this->projectTaskLinks as $tasks) {
                $linksCreated += count($tasks);
            }
        }

        $this->info("Done.");
        $this->table(
            ['Entity', 'Created', 'Existing'],
            [
                ['Clients', $this->clientsCreated, $this->clientsExisting],
                ['Projects', $this->projectsCreated, $this->projectsExisting],
                ['Tasks', $this->tasksCreated, $this->tasksExisting],
            ]
        );
        $this->info("Project-task links" . ($dryRun ? ' (would be created)' : ' created') . ": {$linksCreated}");

        if ($this->errors > 0) {
            $this->warn("Errors: {$this->errors}");
        }

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function processRow(array $row, bool $dryRun): void
    {
        $clientName = $row[1];
        $projectName = $row[2];
        $projectCode = $row[3];
        $taskName = $row[4];
        $billableStr = $row[7];

        $isBillable = strtolower($billableStr) === 'yes';

        $clientId = $this->resolveClient($clientName, $dryRun);
        $projectId = $this->resolveProject($projectName, $projectCode, $clientId, $dryRun);
        $taskId = $this->resolveTask($taskName, $isBillable, $dryRun);

        // Track project-task link (first occurrence wins for is_billable)
        if (! isset($this->projectTaskLinks[$projectId][$taskId])) {
            $this->projectTaskLinks[$projectId][$taskId] = $isBillable;
        }
    }

    private function resolveClient(string $name, bool $dryRun): int
    {
        $key = strtolower($name);

        if (isset($this->clientCache[$key])) {
            return $this->clientCache[$key];
        }

        $client = Client::whereRaw('LOWER(name) = ?', [$key])->first();

        if ($client === null) {
            if (! $dryRun) {
                $client = Client::create(['name' => $name]);
                $this->line("  Created client: {$name}");
            }
            $this->clientsCreated++;
            $this->clientCache[$key] = $dryRun ? 0 : $client->id;
        } else {
            $this->clientsExisting++;
            $this->clientCache[$key] = $client->id;
        }

        return $this->clientCache[$key];
    }

    private function resolveProject(string $name, string $code, int $clientId, bool $dryRun): int
    {
        // Key includes client_id so same-named projects under different clients resolve separately
        $key = strtolower($name) . '|' . $clientId;

        if (isset($this->projectCache[$key])) {
            return $this->projectCache[$key];
        }

        $project = Project::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('client_id', $clientId)
            ->first();

        if ($project === null) {
            if (! $dryRun) {
                $project = Project::create([
                    'client_id' => $clientId,
                    'name' => $name,
                    'code' => $code !== '' ? $code : null,
                    'billing_type' => 'hourly',
                ]);
                $this->line("  Created project: {$name}");
            }
            $this->projectsCreated++;
            $this->projectCache[$key] = $dryRun ? 0 : $project->id;
        } else {
            $this->projectsExisting++;
            $this->projectCache[$key] = $project->id;
        }

        return $this->projectCache[$key];
    }

    private function resolveTask(string $name, bool $isBillable, bool $dryRun): int
    {
        $key = strtolower($name);

        if (isset($this->taskCache[$key])) {
            return $this->taskCache[$key];
        }

        $task = Task::whereRaw('LOWER(name) = ?', [$key])->first();

        if ($task === null) {
            if (! $dryRun) {
                $task = Task::create([
                    'name' => $name,
                    'is_default_billable' => $isBillable,
                ]);
                $this->line("  Created task: {$name}");
            }
            $this->tasksCreated++;
            $this->taskCache[$key] = $dryRun ? 0 : $task->id;
        } else {
            $this->tasksExisting++;
            $this->taskCache[$key] = $task->id;
        }

        return $this->taskCache[$key];
    }
}
