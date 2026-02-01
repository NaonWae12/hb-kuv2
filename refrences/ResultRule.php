<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultRule extends Model
{
    protected $fillable = [
        'form_id',
        'condition_type',
        'min_score',
        'max_score',
        'single_score',
        'order',
        'rule_group_id',
    ];

    protected $casts = [
        'min_score' => 'integer',
        'max_score' => 'integer',
        'single_score' => 'integer',
    ];

    // Relationships
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function texts(): HasMany
    {
        return $this->hasMany(ResultRuleText::class)->orderBy('order');
    }
}
