<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Analytics extends Model
{
    protected $table = 'analytics_cache';

    protected $fillable = [
        'type',
        'data',
        'date_range',
        'expires_at'
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime'
    ];

    // Méthode pour récupérer les données en cache
    public static function getCachedData($type, $dateRange, $callback, $minutes = 60)
    {
        $cacheKey = "analytics.{$type}.{$dateRange}";

        return Cache::remember($cacheKey, $minutes * 60, $callback);
    }
}
