<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite applies the final constraint in the create migration because it cannot alter FKs in place.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('contact_tag', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('contact_tag', function (Blueprint $table) {
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('contact_tag', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('contact_tag', function (Blueprint $table) {
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->cascadeOnUpdate();
        });
    }
};
