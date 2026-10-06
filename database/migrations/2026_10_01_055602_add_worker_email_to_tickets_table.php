<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * อีเมลของแรงงาน (จาก CRM ตอนแจ้งเรื่อง) ไว้ส่งแจ้งเตือนเมื่อเจ้าหน้าที่ตอบกลับ
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('worker_email')->nullable()->after('employer');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('worker_email');
        });
    }
};
