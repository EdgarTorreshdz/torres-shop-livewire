<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: keep the log entry even if the acting user is later
            // deleted — the description already captures who/what in plain
            // text, the FK is just for linking back while the user exists.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // e.g. "product.created", "user.role_updated"
            $table->text('description'); // human-readable, already rendered
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // Full before/after snapshot of the affected record (not just a
            // diff of changed fields) — nullable: creations have no
            // old_values, deletions have no new_values.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
