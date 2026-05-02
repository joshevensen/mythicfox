<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This table is owned long-term by phase-20 task 20-001 ("Create files table,
// model, and storage path helper"). It's introduced here because the catalog
// importers (10-005 / 10-006 / 10-009) all need to persist their source CSVs
// for audit, and waiting until phase 20 would leave those tasks half-done.
// 20-001 should adapt to the existing table rather than re-create it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('file_path');
            $table->string('original_filename');
            $table->timestamp('uploaded_at');
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
