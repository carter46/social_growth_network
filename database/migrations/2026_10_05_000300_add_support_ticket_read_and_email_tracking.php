<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_tickets', 'user_read_at')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->timestamp('user_read_at')->nullable();
            });

            // Existing tickets start as read so old staff replies do not show as new.
            DB::table('support_tickets')->update(['user_read_at' => now()]);
        }

        if (! Schema::hasColumn('support_ticket_replies', 'emailed_at')) {
            Schema::table('support_ticket_replies', function (Blueprint $table) {
                $table->timestamp('emailed_at')->nullable();
            });

            // Existing staff replies never trigger a late reminder.
            DB::table('support_ticket_replies')->where('is_staff', true)->update(['emailed_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_ticket_replies', 'emailed_at')) {
            Schema::table('support_ticket_replies', function (Blueprint $table) {
                $table->dropColumn('emailed_at');
            });
        }

        if (Schema::hasColumn('support_tickets', 'user_read_at')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropColumn('user_read_at');
            });
        }
    }
};
