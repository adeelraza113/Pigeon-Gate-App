<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('club_posts', function (Blueprint $table) {
            $table->string('pigeon_name')->nullable(); 
            $table->string('champion_year')->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_posts', function (Blueprint $table) {
            $table->dropColumn('pigeon_name');
            $table->dropColumn('champion_year');
        });
    }
};
