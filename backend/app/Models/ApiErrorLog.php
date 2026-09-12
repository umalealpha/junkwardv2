<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * API Error / Request log — V2-owned, lives on graphite-v2-prod.
 *
 * Pre-pivot used 'mysql2' (a separate DB on V1 master). Post-pivot,
 * the table is on graphite-v2-prod via the 'mysql_system' connection.
 *
 * JSON columns (request_data, response_data, error_data) are auto-cast
 * to arrays so you can do: $log->error_data['class']
 *
 * Virtual columns (is_error, error_class) are indexed for fast filtering.
 */
class ApiErrorLog extends Model
{
    protected $connection = 'mysql_system';
    protected $table      = 'api_error_log';

    // No updated_at — logs are immutable once written
    public $timestamps    = false;

    protected $fillable = [
        'trace_id', 'method', 'url', 'route', 'status_code', 'duration_ms',
        'user_id', 'user_name', 'request_data', 'response_data', 'error_data',
        'is_investigated', 'investigation_note', 'investigated_by', 'investigated_at',
        'created_at',
    ];

    protected $casts = [
        'request_data'    => 'array',
        'response_data'   => 'array',
        'error_data'      => 'array',
        'is_error'        => 'boolean',
        'is_investigated' => 'boolean',
        'created_at'      => 'datetime',
        'investigated_at' => 'datetime',
    ];

    // =========================================================================
    //  Scopes — chainable for the admin query builder
    // =========================================================================

    public function scopeErrors(Builder $q): Builder
    {
        return $q->whereNotNull('error_data');
    }

    public function scopeStatus(Builder $q, int $min, int $max = 599): Builder
    {
        return $q->whereBetween('status_code', [$min, $max]);
    }

    public function scopeUninvestigated(Builder $q): Builder
    {
        return $q->where('is_investigated', 0);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeUrlContains(Builder $q, string $str): Builder
    {
        return $q->where('url', 'like', '%' . $str . '%');
    }

    public function scopeRouteContains(Builder $q, string $str): Builder
    {
        return $q->where('route', 'like', '%' . $str . '%');
    }

    public function scopeErrorClass(Builder $q, string $class): Builder
    {
        return $q->where('error_class', 'like', '%' . $class . '%');
    }

    /**
     * JSON path filter — translates a dot-notation path to a MySQL JSON_EXTRACT expression.
     *
     * Examples:
     *   jsonPath('error_data.message',    'contains', 'QueryException')
     *   jsonPath('request_data.body.amount', '>', 1000)
     *   jsonPath('response_data.status',  '=',  500)
     */
    public function scopeJsonPath(Builder $q, string $path, string $op, string $value): Builder
    {
        // Convert dot notation → MySQL JSON path: error_data.class → $.class in error_data column
        [$column, $jsonPath] = self::parsePath($path);

        // The column part is interpolated into raw SQL below, so it MUST be an
        // allow-listed JSON column — never free-form user input (blind-SQLi guard).
        $jsonColumns = ['error_data', 'request_data', 'response_data'];
        if (!in_array($column, $jsonColumns, true)) {
            return $q; // unknown column → ignore the filter rather than inject
        }
        $extract = "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$jsonPath}'))";

        $allowedOps = ['=', '!=', '>', '>=', '<', '<=', 'contains', 'starts_with', 'ends_with'];
        if (!in_array($op, $allowedOps)) $op = '=';

        return match ($op) {
            'contains'    => $q->whereRaw("{$extract} LIKE ?", ['%' . $value . '%']),
            'starts_with' => $q->whereRaw("{$extract} LIKE ?", [$value . '%']),
            'ends_with'   => $q->whereRaw("{$extract} LIKE ?", ['%' . $value]),
            default       => $q->whereRaw("{$extract} {$op} ?", [$value]),
        };
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /** "error_data.message" → ['error_data', 'message'] */
    private static function parsePath(string $dotPath): array
    {
        $parts  = explode('.', $dotPath, 2);
        $column = $parts[0];
        $rest   = $parts[1] ?? '';
        $safe   = preg_replace('/[^a-zA-Z0-9._\-]/', '', $rest); // sanitise
        return [$column, $safe];
    }

    /** Convenience: human-readable status badge class */
    public function statusBadgeClass(): string
    {
        return match (true) {
            $this->status_code >= 500 => 'danger',
            $this->status_code >= 400 => 'warning',
            $this->status_code >= 300 => 'info',
            default                   => 'success',
        };
    }

    /** Short method label (colour-coded in view) */
    public function methodBadgeClass(): string
    {
        return match ($this->method) {
            'GET'    => 'primary',
            'POST'   => 'success',
            'PUT','PATCH' => 'warning',
            'DELETE' => 'danger',
            default  => 'secondary',
        };
    }
}
