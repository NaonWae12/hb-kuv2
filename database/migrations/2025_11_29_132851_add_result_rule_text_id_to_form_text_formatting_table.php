<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If the base table doesn't exist yet (fresh installs), this migration would fail.
        // The latest schema is handled by the create migration.
        if (!Schema::hasTable('form_text_formatting')) {
            return;
        }

        Schema::table('form_text_formatting', function (Blueprint $table) {
            // Add result_rule_text_id column if it doesn't exist
            if (!Schema::hasColumn('form_text_formatting', 'result_rule_text_id')) {
                $table->foreignId('result_rule_text_id')->nullable()->after('section_id')->constrained('result_rule_texts')->onDelete('cascade');
            }
            
            // Update enum to include result_setting_title and result_setting_text
            // Note: MySQL doesn't support ALTER ENUM directly, so we need to modify the column
            // Check current enum values first
            try {
                $currentEnum = DB::select("SHOW COLUMNS FROM `form_text_formatting` WHERE Field = 'element_type'")[0] ?? null;
                if ($currentEnum && isset($currentEnum->Type) && strpos($currentEnum->Type, 'result_setting_title') === false) {
                    DB::statement("ALTER TABLE `form_text_formatting` MODIFY COLUMN `element_type` ENUM('form_title', 'form_description', 'question_title', 'section_title', 'section_description', 'result_setting_title', 'result_setting_text') NOT NULL");
                }
            } catch (\Throwable $e) {
                // If enum update fails, proceed; column addition and unique index can still apply.
            }
        });
        
        // Add unique constraint separately to avoid issues
        if (!Schema::hasColumn('form_text_formatting', 'result_rule_text_id')) {
            return;
        }
        
        // Check if unique constraint already exists
        $indexes = DB::select("SHOW INDEXES FROM `form_text_formatting` WHERE Key_name = 'unique_result_rule_text_element'");
        if (empty($indexes)) {
            Schema::table('form_text_formatting', function (Blueprint $table) {
                $table->unique(['result_rule_text_id', 'element_type'], 'unique_result_rule_text_element');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_text_formatting', function (Blueprint $table) {
            // Remove unique constraint
            $table->dropUnique('unique_result_rule_text_element');
            
            // Revert enum
            DB::statement("ALTER TABLE `form_text_formatting` MODIFY COLUMN `element_type` ENUM('form_title', 'form_description', 'question_title', 'section_title', 'section_description') NOT NULL");
            
            // Drop foreign key and column
            $table->dropForeign(['result_rule_text_id']);
            $table->dropColumn('result_rule_text_id');
        });
    }
};
