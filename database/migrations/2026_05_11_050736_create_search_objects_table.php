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
        Schema::create('search_objects', function (Blueprint $table) {
            $table->char('objectUUID', 36)->primary();
            $table->char('parcelUUID', 36);
            $table->string('location', 255);
            $table->string('name', 255);
            $table->string('description', 255);
            $table->char('regionUUID', 36)->default('');
            $table->string('gatekeeperURL', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_objects');
    }
};
