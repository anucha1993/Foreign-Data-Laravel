<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'foreign_id', 'worker_name', 'passport', 'employer', 'worker_email', 'locale', 'category', 'subject',
    'field_key', 'field_label', 'current_value', 'requested_value',
    'status', 'admin_unread', 'worker_unread', 'last_activity_at',
])]
class Ticket extends Model
{
    public const CATEGORIES = [
        'problem' => 'แจ้งปัญหา',
        'correction' => 'ขอแก้ไขข้อมูล',
    ];

    public const STATUSES = [
        'open' => 'รอดำเนินการ',
        'in_progress' => 'กำลังดำเนินการ',
        'resolved' => 'ดำเนินการแล้ว',
        'closed' => 'ปิดเรื่อง',
    ];

    /** สีป้ายสถานะ (ใช้ class lv-* เดียวกับหน้าแรงงาน) */
    public const STATUS_LEVEL = [
        'open' => 'warn',
        'in_progress' => 'info',
        'resolved' => 'ok',
        'closed' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'admin_unread' => 'boolean',
            'worker_unread' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // TK-000123 (ใช้ id ที่ได้หลัง insert)
        static::created(function (Ticket $t) {
            $t->forceFill(['code' => 'TK-'.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? $this->category);
    }
}
