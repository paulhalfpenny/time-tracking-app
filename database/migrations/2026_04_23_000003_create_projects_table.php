<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('billing_type', ['hourly', 'fixed_fee', 'non_billable'])->default('hourly');
            $table->decimal('default_hourly_rate', 8, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_archived')->default(false);

            // JDW monthly export fields
            $table->enum('jdw_category', ['programme', 'project', 'support_maintenance'])->nullable();
            $table->integer('jdw_sort_order')->nullable();
            $table->string('jdw_status')->nullable();
            $table->string('jdw_estimated_launch')->nullable();
            $table->text('jdw_description')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('code');
            $table->index(['is_archived', 'client_id']);
            $table->index(['jdw_category', 'jdw_sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
