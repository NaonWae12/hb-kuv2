<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'form_id',
        'section_id',
        'type',
        'title',
        'description',
        'image',
        'image_alignment',
        'image_width',
        'is_required',
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'image_width' => 'integer',
    ];

    // Relationships
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function responseAnswers(): HasMany
    {
        return $this->hasMany(ResponseAnswer::class);
    }

    public function textFormatting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FormTextFormatting::class)->where('element_type', 'question_title');
    }
}
