<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('curriculum_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('source_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resource_type', 32)->index();
            $table->string('title');
            $table->text('url')->nullable();
            $table->string('creator')->nullable();
            $table->string('review_status', 24)->default('unreviewed')->index();
            $table->date('verified_on')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['curriculum_version_id', 'resource_type']);
        });

        Schema::create('learning_resource_unit', function (Blueprint $table) {
            $table->foreignUlid('learning_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('learning_unit_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 24)->default('reference');
            $table->primary(['learning_resource_id', 'learning_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_resource_unit');
        Schema::dropIfExists('learning_resources');
    }
};
