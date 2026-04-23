<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_import_log', function (Blueprint $table) {
            $table->id();
            $table->string('source_harvest_id')->index();
            $table->timestamp('imported_at');
            $table->string('entity_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_import_log');
    }
};
