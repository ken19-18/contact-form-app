<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contacts', 'deteil') && ! Schema::hasColumn('contacts', 'detail')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->renameColumn('deteil', 'detail');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'detail') && ! Schema::hasColumn('contacts', 'deteil')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->renameColumn('detail', 'deteil');
            });
        }
    }
};
