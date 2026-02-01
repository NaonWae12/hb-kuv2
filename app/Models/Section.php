<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'form_id',
        'title',
        'description',
        'image',
        'image_alignment',
        'image_wrap_mode',
        'order',
        'calculate_subtotal',
        'include_in_total',
    ];
    
    protected $casts = [
        'order' => 'integer',
        'calculate_subtotal' => 'boolean',
        'include_in_total' => 'boolean',
    ];

    // Relationships
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function textFormattings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FormTextFormatting::class);
    }
}
