<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('spent_on');
            $table->decimal('hours', 5, 2);
            $table->text('notes')->nullable();
            $table->boolean('is_running')->default(false);
            $table->timestamp('timer_started_at')->nullable();
            $table->boolean('is_billable');
            $table->decimal('billable_rate_snapshot', 8, 2)->nullable();
            $table->decimal('billable_amount', 10, 2);
            $table->timestamp('invoiced_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'spent_on']);
            $table->index(['project_id', 'spent_on']);
            $table->index(['task_id', 'spent_on']);
            $table->index('spent_on');
            $table->index('is_running');
        });
        // Note: MySQL does not support partial unique indexes. The "at most one running
        // timer per user" constraint is enforced in TimeEntryService at the app level.
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
