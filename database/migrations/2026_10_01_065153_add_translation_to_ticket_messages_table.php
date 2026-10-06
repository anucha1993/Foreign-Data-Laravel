<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * คำตอบของเจ้าหน้าที่ที่แปลเป็นภาษาของแรงงานแล้ว (เจ้าหน้าที่ตรวจ/แก้ก่อนส่ง)
     */
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->text('body_translated')->nullable()->after('body');
            $table->string('translated_locale', 5)->nullable()->after('body_translated');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropColumn(['body_translated', 'translated_locale']);
        });
    }
};
