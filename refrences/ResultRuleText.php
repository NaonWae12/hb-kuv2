<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResultRuleText extends Model
{
    protected $fillable = [
        'result_rule_id',
        'result_text',
        'order',
        'rule_group_id',
    ];

    // Relationships
    public function resultRule(): BelongsTo
    {
        return $this->belongsTo(ResultRule::class);
    }

    public function textSetting(): HasOne
    {
        return $this->hasOne(SettingResult::class, 'result_rule_text_id');
    }
}
