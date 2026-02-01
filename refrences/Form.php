<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'slug',
        'theme_color',
        'collect_email',
        'limit_one_response',
        'show_progress_bar',
        'shuffle_questions',
        'is_active',
    ];

    protected $casts = [
        'collect_email' => 'boolean',
        'limit_one_response' => 'boolean',
        'show_progress_bar' => 'boolean',
        'shuffle_questions' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function answerTemplates(): HasMany
    {
        return $this->hasMany(AnswerTemplate::class)->orderBy('order');
    }

    public function resultRules(): HasMany
    {
        return $this->hasMany(ResultRule::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function ruleGroups(): HasMany
    {
        return $this->hasMany(RuleGroup::class);
    }

    public function header(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FormHeader::class);
    }

    public function textFormattings(): HasMany
    {
        return $this->hasMany(FormTextFormatting::class);
    }
}
