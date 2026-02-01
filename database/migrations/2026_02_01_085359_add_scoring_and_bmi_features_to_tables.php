<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('use_bmi_formula')->default(false)->after('shuffle_questions');
            $table->json('bmi_mapping')->nullable()->after('use_bmi_formula');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->boolean('calculate_subtotal')->default(false)->after('image_wrap_mode');
            $table->boolean('include_in_total')->default(true)->after('calculate_subtotal');
        });

        Schema::table('form_responses', function (Blueprint $table) {
            $table->json('section_scores')->nullable()->after('total_score');
            $table->json('derived_metrics')->nullable()->after('section_scores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['use_bmi_formula', 'bmi_mapping']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['calculate_subtotal', 'include_in_total']);
        });

        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropColumn(['section_scores', 'derived_metrics']);
        });
    }
};
