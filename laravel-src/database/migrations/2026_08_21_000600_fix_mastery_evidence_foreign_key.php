<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mastery_evidence', function (Blueprint $table) {
            $table->dropForeign(['concept_mastery_id']);
            $table->foreign('concept_mastery_id')->references('id')->on('concept_mastery')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mastery_evidence', function (Blueprint $table) {
            $table->dropForeign(['concept_mastery_id']);
            $table->foreign('concept_mastery_id')->references('id')->on('concept_masteries')->cascadeOnDelete();
        });
    }
};
