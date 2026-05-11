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
        Schema::create('search_hostsregister', function (Blueprint $table) {
            $table->string('hostURI', 261)->primary();
            $table->string('host', 255);
            $table->integer('port');
            $table->integer('register');
            $table->integer('nextCheck');
            $table->boolean('checked');
            $table->integer('failCounter');
            $table->string('gatekeeperURL', 255)->nullable();

            $table->unique(['host', 'port']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_hostsregister');
    }
};
