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
        Schema::create('search_regions', function (Blueprint $table) {
            $table->string('regionName', 255);
            $table->char('regionUUID', 36)->primary();
            $table->string('regionHandle', 255);
            $table->string('url', 255);
            $table->string('owner', 255);
            $table->char('ownerUUID', 36);
            $table->string('gatekeeperURL', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_regions');
    }
};
