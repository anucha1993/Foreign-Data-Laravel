<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            // แรงงานเจ้าของเรื่อง (record id ใน Zoho CRM) + ข้อมูล ณ ตอนแจ้ง ไว้แสดงในหน้า Admin
            $table->string('foreign_id', 25)->index();
            $table->string('worker_name')->default('');
            $table->string('passport', 30)->default('');
            $table->string('employer')->default('');
            $table->string('locale', 5)->default('th');
            $table->string('category', 20); // problem | correction
            $table->string('subject');
            // ขอแก้ไขข้อมูล
            $table->string('field_key', 64)->nullable();
            $table->string('field_label')->nullable();
            $table->text('current_value')->nullable();
            $table->text('requested_value')->nullable();
            $table->string('status', 20)->default('open')->index(); // open | in_progress | resolved | closed
            $table->boolean('admin_unread')->default(true);
            $table->boolean('worker_unread')->default(false);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 10); // worker | admin
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
