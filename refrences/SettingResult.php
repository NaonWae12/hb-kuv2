<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingResult extends Model
{
    protected $table = 'setting_results';
    
    protected $fillable = [
        'form_id',
        'rule_group_id',
        'card_title',
        'result_rule_text_id',
        'title',
        'card_image',
        'image',
        'image_alignment',
        'text_alignment',
        'order',
        'card_order',
    ];

    protected $casts = [
        'order' => 'integer',
        'card_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function resultRuleText(): BelongsTo
    {
        return $this->belongsTo(ResultRuleText::class);
    }
}

