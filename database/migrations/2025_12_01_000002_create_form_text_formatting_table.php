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
        Schema::create('form_text_formatting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained('forms')->onDelete('cascade');
            $table->foreignId('question_id')->nullable()->constrained('questions')->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained('sections')->onDelete('cascade');
            $table->foreignId('result_rule_text_id')->nullable()->constrained('result_rule_texts')->onDelete('cascade');
            $table->enum('element_type', [
                'form_title',
                'form_description',
                'question_title',
                'section_title',
                'section_description',
                'result_setting_title',
                'result_setting_text',
            ]);
            $table->string('text_align')->default('left'); // left, center, right, justify
            $table->string('font_family')->default('Arial');
            $table->integer('font_size')->default(12); // in pixels
            $table->string('font_weight')->default('normal'); // normal, bold
            $table->string('font_style')->default('normal'); // normal, italic
            $table->string('text_decoration')->default('none'); // none, underline
            $table->timestamps();

            // Ensure one formatting record per element
            $table->unique(['form_id', 'element_type'], 'unique_form_element');
            $table->unique(['question_id', 'element_type'], 'unique_question_element');
            $table->unique(['section_id', 'element_type'], 'unique_section_element');
            $table->unique(['result_rule_text_id', 'element_type'], 'unique_result_rule_text_element');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_text_formatting');
    }
};
